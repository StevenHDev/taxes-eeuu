<?php

namespace App\Models;

use App\Enums\EventSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $forma
 * @property string $campo
 * @property mixed $valor_anterior
 * @property mixed $valor_nuevo
 * @property EventSource $source
 * @property int|null $modificado_por
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'forma',
    'campo',
    'valor_anterior',
    'valor_nuevo',
    'source',
    'modificado_por',
])]
class HistorialCambio extends Model
{
    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_anterior' => 'encrypted:array',
            'valor_nuevo' => 'encrypted:array',
            'source' => EventSource::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function modificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }
}
