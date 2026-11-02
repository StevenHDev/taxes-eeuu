<?php

namespace App\Services\WhatsappAgent;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\FaseConversacion;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaGuardador;

/**
 * Deriva la fase vigente de una conversación de WhatsApp a partir de los
 * datos ya existentes del cliente — nunca de memoria de la conversación (ver
 * FaseConversacion). Sin cliente, la fase es siempre VerificacionCuenta.
 *
 * Con cliente (F2.8), la fase sale del consentimiento y de MotorEntrevista:
 * sin consentimiento, Consentimiento; con preguntas no sensibles por
 * responder y la entrevista por WhatsApp habilitada, Entrevista; si no,
 * Seguimiento. En el chat del portal, PortalDudas.
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

    /**
     * Si el agente puede hacer la entrevista por WhatsApp con este cliente:
     * los usuarios de prueba siempre; los reales solo con
     * `tax.entrevista_whatsapp` encendido (apagado hasta L3, decisión 7).
     */
    public static function entrevistaPorWhatsapp(User $cliente): bool
    {
        return $cliente->es_prueba || (bool) config('tax.entrevista_whatsapp');
    }
}
