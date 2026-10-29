<?php

namespace App\Models\CatalogoFiscal\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fuente del IRS y estado de validación de una pieza del catálogo
 * (principio 1 y 7.7 del documento madre). "Sin validar" significa que
 * todavía nadie la comparó contra la publicación del IRS.
 *
 * @property string|null $fuente_texto
 * @property string|null $fuente_url
 * @property int|null $validado_por
 * @property Carbon|null $validado_en
 */
trait TieneFuenteIrs
{
    public function initializeTieneFuenteIrs(): void
    {
        $this->mergeCasts(['validado_en' => 'datetime']);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function estaValidado(): bool
    {
        return $this->validado_en !== null;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSinValidar(Builder $query): void
    {
        $query->whereNull('validado_en');
    }
}
