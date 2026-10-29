<?php

namespace App\Models\CatalogoFiscal\Concerns;

use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comportamiento común de las piezas del catálogo fiscal: pertenecen a un
 * año, se desactivan en vez de borrarse y, al guardarse, invalidan la caché
 * de su año.
 *
 * @property int $tax_year
 * @property bool $activo
 */
trait EsDelCatalogoFiscal
{
    public static function bootEsDelCatalogoFiscal(): void
    {
        static::saved(fn ($model) => CatalogoFiscal::invalidar($model->tax_year));
    }

    public function initializeEsDelCatalogoFiscal(): void
    {
        $this->mergeCasts(['tax_year' => 'integer', 'activo' => 'boolean']);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeDelAnio(Builder $query, int $taxYear): void
    {
        $query->where('tax_year', $taxYear);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
