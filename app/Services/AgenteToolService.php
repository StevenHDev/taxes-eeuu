<?php

namespace App\Services;

use App\Enums\FormState;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Models\CampoCatalogo;
use App\Models\ClienteAtestacion;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Notifications\BienvenidaClientePortal;
use App\Services\WhatsappAgent\ActivosResolver;
use App\Support\TaxFieldCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Lógica compartida detrás de 4 de las 5 tools del agente conversacional
 * (`crear_cliente_taxes`, `declarar_formas_cliente`, `consultar_pendientes_cliente`,
 * `consultar_documentos_extra` — la quinta, `guardar_campo_cliente`, vive en
 * EventoRecoleccionService) — invocada tanto por los controladores HTTP en
 * `Api\ClienteController`/`Api\CatalogoController` (agente externo actual)
 * como por `ToolExecutor` (agente de WhatsApp, sin HTTP). Ver decisión de
 * arquitectura sobre EventoRecoleccionService en docs/implementar_agente_n8n.md
 * — mismo razonamiento aplicado acá.
 */
class AgenteToolService
{
    public function __construct(
        private readonly ActivosResolver $activosResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  con name, email, phone y preparer_id opcionales — mismo shape que ClienteStoreRequest::validated()
     */
    public function crearCliente(array $datos, User $actor): User
    {
        $cliente = User::query()->create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'phone' => $datos['phone'] ?? null,
            'password' => Hash::make(Str::random(40)),
            'role' => UserRole::Client,
            'preparer_id' => $actor->role === UserRole::Preparer ? $actor->id : ($datos['preparer_id'] ?? null),
        ]);

        // La contraseña de arriba es aleatoria y nadie la conoce — sin este
        // aviso, el cliente nunca podría entrar al portal seguro (ver
        // App\Notifications\BienvenidaClientePortal).
        $cliente->notify(new BienvenidaClientePortal(Password::createToken($cliente)));

        return $cliente;
    }

    /**
     * Idempotente: usa firstOrCreate para no resetear a en_progreso una forma
     * que ya esté completa si el agente vuelve a declarar formas más adelante
     * en la conversación (ej. el cliente menciona una situación adicional).
     *
     * @param  array<int, string>  $formas
     * @return array<string, mixed>
     */
    public function declararFormas(User $cliente, int $taxYear, array $formas): array
    {
        foreach ($formas as $forma) {
            FormaCliente::query()->firstOrCreate(
                ['user_id' => $cliente->id, 'forma' => $forma, 'tax_year' => $taxYear],
                ['estado' => FormState::EnProgreso],
            );
        }

        return $this->pendientes($cliente, $taxYear);
    }

    /**
     * Qué le falta a un cliente por entregar, a través de todas sus formas
     * declaradas — para que el agente sepa qué pedir a continuación sin tener
     * que memorizar el catálogo.
     *
     * @return array<string, mixed>
     */
    public function pendientes(User $cliente, int $taxYear): array
    {
        $formas = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->pluck('forma')
            ->map(fn (string $forma) => TaxForm::tryFrom($forma))
            ->filter()
            ->values()
            ->all();

        // `siguiente` es el PRIMER elemento de `pendientes`, en el orden que ya
        // trae el catálogo (transversales primero, documentos y datos
        // opcionales incluidos) — nunca solo el primer obligatorio (ver
        // Api\ClienteController::pendientesEnvelope, misma lógica histórica).
        $pendientes = TaxFieldCatalog::pendientesPara($taxYear, $formas, $cliente->id);
        $siguiente = $pendientes[0] ?? null;
        $quedaObligatorioPendiente = collect($pendientes)->contains(fn (array $p) => $p['obligatorio']);

        return [
            'tax_year' => $taxYear,
            'completo' => $formas !== [] && ! $quedaObligatorioPendiente,
            'pendientes' => $pendientes,
            'siguiente' => $siguiente ? ['forma' => $siguiente['forma'], 'campo' => $siguiente['campo']] : null,
            // Próximo campo ACTIVO transversal a preguntar, ya resuelto (orden
            // fijo + condiciones aplicadas) — ver ActivosResolver. El agente
            // conversacional debe leerlo tal cual en vez de recalcular el
            // orden de ACTIVOS él mismo turno a turno.
            'siguiente_activo' => $this->activosResolver->siguiente($taxYear, $cliente->id, $pendientes),
            // Fase 4 del plan de cierre de brecha GTS (atestación final de
            // cierre) — ver atestacionVigente() y prompt_actuales/fases/cierre.md.
            'atestacion_vigente' => $this->atestacionVigente($cliente, $taxYear),
        ];
    }

    /**
     * true si el cliente ya confirmó, y esa confirmación sigue vigente — es
     * decir, ningún dato/documento se guardó DESPUÉS de la atestación más
     * reciente. Un cambio posterior (el cliente agregó algo nuevo tras
     * cerrar) invalida la atestación anterior sin necesidad de una columna
     * aparte para marcarlo: alcanza con comparar timestamps contra
     * `historial_cambios`, que ya registra cada guardado (ver
     * EventoRecoleccionService::aplicarCambio()).
     */
    private function atestacionVigente(User $cliente, int $taxYear): bool
    {
        $ultima = ClienteAtestacion::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->latest('confirmado_en')
            ->first();

        if ($ultima === null) {
            return false;
        }

        return ! HistorialCambio::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->where('created_at', '>', $ultima->confirmado_en)
            ->exists();
    }

    /**
     * Registra la confirmación explícita del cliente — ver
     * ClienteAtestacion y prompt_actuales/fases/cierre.md (ATESTACIÓN DE
     * CIERRE) para cuándo se invoca. `$respuesta` es el texto literal que
     * el cliente escribió, nunca una frase canónica inventada por el agente
     * — es el registro legal de lo que el cliente realmente confirmó.
     *
     * @return array<string, mixed>
     */
    public function registrarAtestacion(User $cliente, int $taxYear, string $respuesta): array
    {
        $atestacion = ClienteAtestacion::query()->create([
            'user_id' => $cliente->id,
            'tax_year' => $taxYear,
            'respuesta_cliente' => $respuesta,
            'confirmado_en' => now(),
        ]);

        return ['atestacion_id' => $atestacion->id, 'confirmado_en' => $atestacion->confirmado_en->toISOString()];
    }

    /**
     * Catálogo de documentos opcionales, consultado reactivamente cuando el
     * cliente entrega algo que no coincide con el campo actualmente pedido.
     *
     * @return array<string, mixed>
     */
    public function documentosExtra(int $taxYear): array
    {
        return [
            'tax_year' => $taxYear,
            'documentos' => collect(TaxFieldCatalog::documentosExtra($taxYear))
                ->map(fn (array $f) => [
                    'forma' => CampoCatalogo::DOCUMENTOS_EXTRA,
                    'campo' => $f['campo'],
                    'tipo_campo' => $f['tipo']->value,
                    'tipo_dato' => $f['tipo_dato']?->value,
                    'subcampos' => $f['subcampos'],
                    'formatos_aceptados' => $f['formatos_aceptados'],
                    'obligatorio' => $f['obligatorio'],
                    'sensible' => $f['sensible'],
                    'revela' => TaxFieldCatalog::revelaPara($taxYear, $f['campo']),
                ])
                ->values()
                ->all(),
        ];
    }
}
