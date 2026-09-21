<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una confirmación explícita del cliente, al cierre de la recolección, de
 * que la información y los documentos entregados son completos y correctos
 * — ver la migración `create_cliente_atestaciones_table` para el porqué de
 * la tabla propia (append-only, nunca se actualiza ni se borra una fila) y
 * App\Services\AgenteToolService::atestacionVigente() para cómo se resuelve
 * si la más reciente sigue vigente.
 *
 * @property int $id
 * @property int $user_id
 * @property int $tax_year
 * @property string $respuesta_cliente
 * @property Carbon $confirmado_en
 */
#[Fillable(['user_id', 'tax_year', 'respuesta_cliente', 'confirmado_en'])]
class ClienteAtestacion extends Model
{
    protected $table = 'cliente_atestaciones';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
