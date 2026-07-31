<?php

namespace App\Models;

use App\Enums\TipoDeterminacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Resultado calculado por el motor de reglas (App\Services\DeterminacionFiscalService)
 * para un cliente, en un año fiscal, para un `tipo` dado. Separada a propósito
 * de `campos_cliente`: acá vive "lo que calculó el sistema", nunca "lo que
 * dijo el cliente".
 *
 * @property int $id
 * @property int $user_id
 * @property int $tax_year
 * @property TipoDeterminacion $tipo
 * @property array<string, mixed> $resultado
 * @property string $version_reglas
 * @property Carbon $calculado_en
 */
#[Fillable(['user_id', 'tax_year', 'tipo', 'resultado', 'version_reglas', 'calculado_en'])]
class DeterminacionFiscal extends Model
{
    protected $table = 'determinaciones_fiscales';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDeterminacion::class,
            'resultado' => 'encrypted:array',
            'calculado_en' => 'datetime',
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
