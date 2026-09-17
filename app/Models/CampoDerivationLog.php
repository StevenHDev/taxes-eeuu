<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una fila por documento con relaciones documento→campo declaradas (ver
 * RelacionDocumentoCampo), registrada por
 * EventoRecoleccionService::registrarDerivacion() — ver la migración
 * `create_campo_derivation_logs_table` para el porqué.
 *
 * @property int $id
 * @property int $user_id
 * @property int $tax_year
 * @property int $documento_id
 * @property string $documento_campo
 * @property array<int, array<string, mixed>> $relaciones_esperadas
 * @property array<int, array<string, mixed>> $revelados_recibidos
 * @property array<int, array<string, mixed>> $relaciones_faltantes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'tax_year',
    'documento_id',
    'documento_campo',
    'relaciones_esperadas',
    'revelados_recibidos',
    'relaciones_faltantes',
])]
class CampoDerivationLog extends Model
{
    protected $table = 'campo_derivation_logs';

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relaciones_esperadas' => 'array',
            'revelados_recibidos' => 'array',
            'relaciones_faltantes' => 'array',
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
     * @return BelongsTo<Documento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function tieneFaltantes(): bool
    {
        return $this->relaciones_faltantes !== [];
    }
}
