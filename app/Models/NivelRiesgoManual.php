<?php

namespace App\Models;

use App\Enums\NivelRiesgo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tax_year
 * @property NivelRiesgo $nivel
 * @property int $establecido_por
 * @property Carbon $establecido_en
 */
#[Fillable(['user_id', 'tax_year', 'nivel', 'establecido_por', 'establecido_en'])]
class NivelRiesgoManual extends Model
{
    /**
     * Convención de pluralización de Eloquent daría "nivel_riesgo_manuals" —
     * el nombre real de la tabla sigue el orden del dominio ("niveles de
     * riesgo manual").
     */
    protected $table = 'niveles_riesgo_manual';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => NivelRiesgo::class,
            'establecido_en' => 'datetime',
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
    public function establecidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'establecido_por');
    }
}
