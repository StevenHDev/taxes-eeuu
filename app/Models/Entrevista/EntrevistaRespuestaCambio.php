<?php

namespace App\Models\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Historial de una respuesta: solo se inserta (RespuestaEntrevistaObserver).
 *
 * @property int $id
 * @property int $respuesta_id
 * @property string|int|float|list<string>|null $valor_anterior
 * @property string|int|float|list<string> $valor_nuevo
 * @property CanalRespuesta $canal
 * @property int|null $actor_id
 * @property Carbon $created_at
 */
#[Fillable(['respuesta_id', 'valor_anterior', 'valor_nuevo', 'canal', 'actor_id'])]
class EntrevistaRespuestaCambio extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'entrevista_respuesta_cambios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_anterior' => ValorCifrado::class,
            'valor_nuevo' => ValorCifrado::class,
            'canal' => CanalRespuesta::class,
        ];
    }

    /**
     * @return BelongsTo<EntrevistaRespuesta, $this>
     */
    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(EntrevistaRespuesta::class, 'respuesta_id');
    }
}
