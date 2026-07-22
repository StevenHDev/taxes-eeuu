<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $campo_cliente_id
 * @property int $revealed_by_id
 * @property string|null $ip_address
 */
#[Fillable(['campo_cliente_id', 'revealed_by_id', 'ip_address'])]
class CampoReveal extends Model
{
    const UPDATED_AT = null;

    /**
     * @return BelongsTo<CampoCliente, $this>
     */
    public function campoCliente(): BelongsTo
    {
        return $this->belongsTo(CampoCliente::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revealedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revealed_by_id');
    }
}
