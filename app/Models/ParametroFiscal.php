<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Montos y umbrales del IRS versionados por año fiscal (créditos, límites de
 * dependientes, deducción estándar) — fuente de verdad para el motor de
 * reglas, leída vía App\Support\ParametrosFiscales. Nunca se hardcodea un
 * monto en una calculadora; siempre se lee de acá.
 *
 * @property int $id
 * @property int $tax_year
 * @property string $categoria
 * @property string $clave
 * @property mixed $valor
 */
#[Fillable(['tax_year', 'categoria', 'clave', 'valor'])]
class ParametroFiscal extends Model
{
    protected $table = 'parametros_fiscales';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }
}
