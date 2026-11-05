<?php

namespace App\Services\Expediente;

use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\PerfilExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Events\ExpedienteCambioDeEstado;
use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\User;
use App\Services\Entrevista\MotorEntrevista;
use App\Support\ParametrosFiscales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El único que escribe el estado automático del expediente (y quien registra
 * cada cambio de estado, también los manuales de RevisionExpediente).
 *
 * - `recalcular()` pone al día el expediente con el diagnóstico: los estados
 *   derivados siguen al diagnóstico; la revisión se reabre sola si deja de
 *   estar listo; tras presentar nada cambia solo, solo se marca la alerta.
 * - `pedirRecalculo()` lo pide después del commit, una sola vez por cliente y
 *   año. Se registra como `scoped`: la lista de pendientes vive en la petición.
 * - Eventos y motivos nunca llevan SSN, montos ni datos del bloque C.
 */
class EstadosExpediente
{
    /** @var array<string, true> */
    private array $pendientes = [];

    public function __construct(
        private readonly DiagnosticoExpediente $diagnostico,
        private readonly MotorEntrevista $motor,
    ) {}

    public function recalcular(User $cliente, int $taxYear): Expediente
    {
        return DB::transaction(function () use ($cliente, $taxYear) {
            $entrevista = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();

            $existente = Expediente::query()->firstOrCreate(
                ['user_id' => $cliente->id, 'tax_year' => $taxYear],
                ['estado' => EstadoExpediente::Invitado, 'estado_desde' => now(), 'entrevista_id' => $entrevista?->id],
            );

            // Bloqueo de la fila, sin agregados en la misma consulta.
            $expediente = Expediente::query()->whereKey($existente->id)->lockForUpdate()->firstOrFail();

            $diagnostico = $this->diagnostico->para($cliente, $taxYear);
            [$perfil, $venceEn] = $this->perfilYVencimiento($entrevista, $taxYear);

            $expediente->fill([
                'entrevista_id' => $entrevista?->id,
                'perfil' => $perfil,
                'vence_en' => $venceEn,
            ]);

            if ($expediente->isDirty()) {
                $expediente->save();
            }

            $estado = $expediente->estado;

            if ($estado->derivado()) {
                if ($diagnostico->estado !== $estado) {
                    $this->registrarCambio($expediente, $diagnostico->estado, null);
                }
            } elseif ($estado->reabrible() && ! $diagnostico->listo()) {
                $this->borrarConfirmaciones($expediente);
                $this->registrarCambio($expediente, $diagnostico->estado, null, TipoEventoExpediente::Reabierto, implode('; ', $diagnostico->motivos()));
            } elseif ($estado->trasPresentar() && ! $diagnostico->listo() && $expediente->cambios_tras_presentar_en === null) {
                $expediente->update(['cambios_tras_presentar_en' => now()]);
                ExpedienteEvento::query()->create([
                    'expediente_id' => $expediente->id,
                    'tipo' => TipoEventoExpediente::CambioTrasPresentar,
                    'motivo' => implode('; ', $diagnostico->motivos()),
                ]);
            }

            return $expediente;
        });
    }

    /**
     * Pide recalcular después del commit y sin repetir el mismo cliente y año
     * mientras esté pendiente. Fuera de una transacción corre en el momento.
     */
    public function pedirRecalculo(int $clienteId, int $taxYear): void
    {
        $clave = "{$clienteId}#{$taxYear}";

        if (isset($this->pendientes[$clave])) {
            return;
        }

        $this->pendientes[$clave] = true;

        DB::afterCommit(function () use ($clave, $clienteId, $taxYear) {
            unset($this->pendientes[$clave]);

            // El recálculo corre dentro de lo que el cliente está haciendo (responder, subir un archivo): si falla, se
            // registra y la operación del cliente sigue. El estado se corrige en el siguiente cambio o con expedientes:recalcular.
            try {
                $cliente = User::withTrashed()->find($clienteId);

                if ($cliente !== null) {
                    $this->recalcular($cliente, $taxYear);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        // Si la transacción se deshace, el pedido se descarta: que no quede pendiente.
        if (DB::transactionLevel() > 0) {
            DB::afterRollBack(function () use ($clave) {
                unset($this->pendientes[$clave]);
            });
        }
    }

    /**
     * Escribe el estado y su evento. Lo usan el recálculo y las transiciones
     * manuales. `actor` nulo = la plataforma.
     *
     * @param  array<string, mixed>  $datos  nunca valores sensibles
     */
    public function registrarCambio(Expediente $expediente, EstadoExpediente $nuevo, ?User $actor, TipoEventoExpediente $tipo = TipoEventoExpediente::Estado, ?string $motivo = null, array $datos = []): void
    {
        $anterior = $expediente->estado;

        DB::transaction(function () use ($expediente, $nuevo, $actor, $tipo, $motivo, $datos, $anterior) {
            $expediente->update(['estado' => $nuevo, 'estado_desde' => now()]);

            ExpedienteEvento::query()->create([
                'expediente_id' => $expediente->id,
                'tipo' => $tipo,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $nuevo,
                'actor_id' => $actor?->id,
                'motivo' => $motivo,
                'datos' => $datos === [] ? null : $datos,
            ]);
        });

        if ($anterior !== $nuevo) {
            ExpedienteCambioDeEstado::dispatch($expediente, $anterior, $nuevo, $actor);
        }
    }

    /**
     * Reabrir la revisión deja los puntos manuales sin confirmar.
     */
    private function borrarConfirmaciones(Expediente $expediente): void
    {
        $expediente->puntos()->update(['confirmado_por' => null, 'confirmado_en' => null]);
    }

    /**
     * @return array{0: list<string>, 1: Carbon|null}
     */
    private function perfilYVencimiento(?Entrevista $entrevista, int $taxYear): array
    {
        $situacion = $entrevista === null ? null : $this->motor->situacion($entrevista->unsetRelation('respuestas')->unsetRelation('personas'));
        $tiene = fn (string ...$codigos) => $situacion !== null && array_filter($codigos, fn (string $c) => $situacion->tieneDocumento($c)) !== [];

        $perfil = array_keys(array_filter([
            PerfilExpediente::Asalariado->value => $tiene('w2'),
            PerfilExpediente::Independiente->value => $situacion?->tieneFormulario('sch-c') ?? false,
            PerfilExpediente::Jubilacion->value => $tiene('1099-r', 'ssa-1099'),
            PerfilExpediente::Inversiones->value => $tiene('1099-int', '1099-div', '1099-b', '1099-da'),
            PerfilExpediente::Alquiler->value => $tiene('registros-alquiler'),
            PerfilExpediente::Marketplace->value => $tiene('1095-a'),
            PerfilExpediente::K1->value => $tiene('k-1'),
            PerfilExpediente::Dependientes->value => $entrevista !== null && $entrevista->personas()->exists(),
        ]));

        $clave = ($situacion?->respuestas['G2'] ?? null) === 'si' ? 'extension' : 'presentacion';
        $fecha = ParametrosFiscales::valor($taxYear, 'vencimientos', $clave);

        return [$perfil, is_string($fecha) && $fecha !== '' ? Carbon::parse($fecha)->startOfDay() : null];
    }
}
