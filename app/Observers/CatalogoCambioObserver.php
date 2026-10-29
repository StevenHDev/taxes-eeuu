<?php

namespace App\Observers;

use App\Enums\CatalogoFiscal\OrigenCambio;
use App\Models\CatalogoFiscal\CatalogoCambio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Historial del catálogo fiscal (F1.1, 7.7): por cada atributo que cambia
 * guarda el valor anterior y el nuevo, quién lo hizo y si vino de la
 * pantalla o de la carga desde archivos. A diferencia de AuditoriaObserver
 * sí guarda valores, porque el catálogo no tiene datos de clientes.
 *
 * No atrapa errores: si el historial no se puede escribir, el cambio
 * tampoco debe quedar (se guarda en la misma transacción).
 */
class CatalogoCambioObserver
{
    private const IGNORADOS = ['updated_at', 'created_at'];

    public function updated(Model $model): void
    {
        $actor = Auth::user();

        foreach (array_keys($model->getChanges()) as $campo) {
            if (in_array($campo, self::IGNORADOS, true)) {
                continue;
            }

            CatalogoCambio::query()->create([
                'tax_year' => $model->getAttribute('tax_year'),
                'auditable_type' => $model::class,
                'auditable_id' => $model->getKey(),
                'campo' => $campo,
                'valor_anterior' => $model->getOriginal($campo),
                'valor_nuevo' => $model->getAttribute($campo),
                'actor_id' => $actor?->getKey(),
                'origen' => $actor ? OrigenCambio::Pantalla : OrigenCambio::Carga,
            ]);
        }
    }
}
