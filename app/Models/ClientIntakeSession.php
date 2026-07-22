<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deduplica la creación de clientes cuando el agente conversacional envía
 * `cliente_id: null` más de una vez para la misma conversación externa
 * (identificada por `external_ref`, un campo opcional que extiende el
 * evento del spec original para evitar clientes duplicados).
 *
 * @property int $id
 * @property string $external_ref
 * @property int $user_id
 */
#[Fillable(['external_ref', 'user_id'])]
class ClientIntakeSession extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
