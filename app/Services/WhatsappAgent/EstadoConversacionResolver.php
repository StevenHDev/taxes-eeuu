<?php

namespace App\Services\WhatsappAgent;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\FaseConversacion;
use App\Enums\TaxForm;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaGuardador;
use App\Support\TaxFieldCatalog;

/**
 * Deriva la fase vigente de una conversación de WhatsApp a partir de los
 * datos ya existentes del cliente — nunca de memoria de la conversación (ver
 * FaseConversacion). Sin cliente, la fase es siempre VerificacionCuenta.
 *
 * Clientes con la entrevista nueva (F2.8): la fase sale del consentimiento
 * y de MotorEntrevista. Sin consentimiento, Consentimiento; con preguntas
 * no sensibles por responder y la entrevista por WhatsApp habilitada,
 * Entrevista; si no, Seguimiento. En el chat del portal, PortalDudas.
 *
 * Resto de los clientes (flujo actual, hasta 2.11): el año fiscal "en
 * curso" es el mayor `tax_year` entre las formas ya declaradas de este
 * cliente (`declarar_formas_cliente` es la única tool que recibe tax_year
 * como parámetro y lo persiste, vía FormaCliente) — no se guarda en ningún
 * otro lugar, igual que en el diseño anterior el orquestador de n8n tampoco
 * lo persistía: lo recordaba de su propio contexto de conversación mientras
 * durara la fase DeterminacionFormas.
 */
class EstadoConversacionResolver
{
    public function __construct(
        private readonly Consentimientos $consentimientos,
        private readonly MotorEntrevista $motor,
        private readonly RespuestaGuardador $guardador,
    ) {}

    public function resolver(?User $cliente, bool $canalPortal = false): FaseConversacion
    {
        if ($cliente === null) {
            return FaseConversacion::VerificacionCuenta;
        }

        if ($cliente->usaEntrevistaCatalogo()) {
            return $this->faseEntrevistaNueva($cliente, $canalPortal);
        }

        $taxYear = FormaCliente::query()->where('user_id', $cliente->id)->max('tax_year');

        if ($taxYear === null) {
            return FaseConversacion::DeterminacionFormas;
        }

        $formas = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->pluck('forma')
            ->map(fn (string $forma) => TaxForm::tryFrom($forma))
            ->filter()
            ->values()
            ->all();

        $pendientes = TaxFieldCatalog::pendientesPara((int) $taxYear, $formas, $cliente->id);
        $quedaObligatorioPendiente = collect($pendientes)->contains(fn (array $p) => $p['obligatorio']);

        return $quedaObligatorioPendiente ? FaseConversacion::Recoleccion : FaseConversacion::Cierre;
    }

    /**
     * Si el agente puede hacer la entrevista por WhatsApp con este cliente:
     * los usuarios de prueba siempre; los reales solo con
     * `tax.entrevista_whatsapp` encendido (apagado hasta L3, decisión 7).
     */
    public static function entrevistaPorWhatsapp(User $cliente): bool
    {
        return $cliente->es_prueba || (bool) config('tax.entrevista_whatsapp');
    }

    private function faseEntrevistaNueva(User $cliente, bool $canalPortal): FaseConversacion
    {
        if ($canalPortal) {
            return FaseConversacion::PortalDudas;
        }

        if (! $this->consentimientos->firmado($cliente, (int) config('tax.current_tax_year'))) {
            return FaseConversacion::Consentimiento;
        }

        if (! self::entrevistaPorWhatsapp($cliente)) {
            return FaseConversacion::Seguimiento;
        }

        $situacion = $this->motor->situacion($this->guardador->entrevistaDe($cliente, CanalRespuesta::Whatsapp));

        return $situacion->pendientesParaWhatsapp() !== []
            ? FaseConversacion::Entrevista
            : FaseConversacion::Seguimiento;
    }
}
