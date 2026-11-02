<?php

namespace App\Models\Entrevista;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quién reveló una respuesta sensible de la entrevista y desde dónde
 * (F2.9, como CampoReveal). Solo se inserta.
 *
 * @property int $id
 * @property int $respuesta_id
 * @property int $revealed_by_id
 * @property string|null $ip_address
 */
#[Fillable(['respuesta_id', 'revealed_by_id', 'ip_address'])]
class EntrevistaRespuestaReveal extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'entrevista_respuesta_reveals';

    /**
     * @return BelongsTo<EntrevistaRespuesta, $this>
     */
    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(EntrevistaRespuesta::class, 'respuesta_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revealedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revealed_by_id');
    }
}
