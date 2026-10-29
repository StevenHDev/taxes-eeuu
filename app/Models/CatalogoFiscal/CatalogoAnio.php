<?php

namespace App\Models\CatalogoFiscal;

use App\Enums\CatalogoFiscal\EstadoAnio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un año fiscal del catálogo y su estado (F1.1).
 *
 * @property int $id
 * @property int $tax_year
 * @property EstadoAnio $estado
 * @property int|null $copiado_de
 */
#[Fillable(['tax_year', 'estado', 'copiado_de'])]
class CatalogoAnio extends Model
{
    protected $table = 'catalogo_anios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'estado' => EstadoAnio::class,
            'copiado_de' => 'integer',
        ];
    }
}
