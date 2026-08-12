<?php

namespace App\Observers;

use App\Enums\AccionAuditoria;
use App\Models\BitacoraActividad;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\NivelRiesgoManual;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Observer genérico registrado explícitamente (ver AppServiceProvider) sobre
 * los modelos con un punto de entrada de escritura real. Nunca guarda el
 * valor de un atributo — solo su nombre — para no duplicar datos sensibles
 * (SSN, cuentas bancarias) fuera del cifrado/enmascarado que ya maneja
 * `CampoCliente`/`HistorialCambio` (ver la migración de `bitacora_actividad`
 * para el razonamiento completo).
 */
class AuditoriaObserver
{
    public function created(Model $model): void
    {
        $this->registrar($model, AccionAuditoria::Creado);
    }

    public function updated(Model $model): void
    {
        $cambios = array_values(array_diff(array_keys($model->getChanges()), ['updated_at']));

        // Eloquent ya evita disparar el evento 'updated' cuando ningún atributo
        // cambió de verdad — esto es una red de seguridad adicional, no el
        // filtro principal.
        if ($cambios === []) {
            return;
        }

        $this->registrar($model, AccionAuditoria::Actualizado, $cambios);
    }

    public function deleted(Model $model): void
    {
        $this->registrar($model, AccionAuditoria::Eliminado);
    }

    /**
     * @param  array<int, string>|null  $campos
     */
    private function registrar(Model $model, AccionAuditoria $accion, ?array $campos = null): void
    {
        // Nunca debe tumbar la acción real que está auditando (ej. guardar un
        // cliente) — ni por una falla puntual de la bitácora, ni porque una
        // migración de datos modifique un modelo auditado antes de que exista
        // la tabla `bitacora_actividad` (ambos casos ya ocurrieron en este
        // repo: migraciones que hacen CampoCatalogo::update() directo).
        try {
            $actor = Auth::user();

            BitacoraActividad::query()->create([
                'actor_id' => $actor?->id,
                'actor_nombre' => $actor?->name,
                'actor_email' => $actor?->email,
                'accion' => $accion,
                'auditable_type' => $model::class,
                'auditable_id' => $model->getKey(),
                'etiqueta' => $this->etiquetaPara($model),
                'campos_afectados' => $campos,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar el evento en la bitácora de actividad.', [
                'modelo' => $model::class,
                'accion' => $accion->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function etiquetaPara(Model $model): string
    {
        return match (true) {
            $model instanceof User => "{$model->name} ({$model->role->label()})",
            $model instanceof CampoCatalogo => "{$model->clave} ({$model->forma})",
            $model instanceof CampoCliente => "{$model->campo} ({$model->forma}, cliente #{$model->user_id})",
            $model instanceof Documento => ($model->file_original_name ?? $model->campo)." (cliente #{$model->user_id})",
            $model instanceof FormaCliente => "{$model->forma} (cliente #{$model->user_id})",
            $model instanceof DeterminacionFiscal => "{$model->tipo->value} (cliente #{$model->user_id})",
            $model instanceof NivelRiesgoManual => "cliente #{$model->user_id}",
            default => class_basename($model),
        };
    }
}
