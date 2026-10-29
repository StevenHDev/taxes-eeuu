<?php

namespace App\Models\CatalogoFiscal;

use App\Enums\CatalogoFiscal\OrigenCambio;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Historial del catálogo fiscal con el valor anterior y el nuevo (7.7). Solo
 * se inserta; lo escribe `CatalogoCambioObserver`.
 *
 * @property int $id
 * @property int $tax_year
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string $campo
 * @property mixed $valor_anterior
 * @property mixed $valor_nuevo
 * @property int|null $actor_id
 * @property OrigenCambio $origen
 */
#[Fillable(['tax_year', 'auditable_type', 'auditable_id', 'campo', 'valor_anterior', 'valor_nuevo', 'actor_id', 'origen'])]
class CatalogoCambio extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'catalogo_cambios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'valor_anterior' => 'json',
            'valor_nuevo' => 'json',
            'origen' => OrigenCambio::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
