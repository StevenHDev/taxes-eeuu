<?php

namespace App\Models;

use App\Enums\CatalogoFiscal\GrupoParametro;
use App\Enums\CatalogoFiscal\TipoParametro;
use App\Models\CatalogoFiscal\Concerns\TieneFuenteIrs;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Support\ParametrosFiscales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Montos y umbrales del IRS versionados por año fiscal (créditos, límites de
 * dependientes, deducción estándar) — fuente de verdad para el motor de
 * reglas, leída vía App\Support\ParametrosFiscales. Nunca se hardcodea un
 * monto en una calculadora; siempre se lee de acá.
 *
 * Desde F1.2 también es la pestaña "Parámetros" del catálogo fiscal (7.7):
 * grupo, nombre, condición, formulario y línea, fuente del IRS y estado de
 * validación. `valor` nulo = "pendiente" (sin cifra del IRS todavía).
 *
 * @property int $id
 * @property int $tax_year
 * @property string $categoria
 * @property string $clave
 * @property mixed $valor
 * @property GrupoParametro|null $grupo
 * @property string|null $nombre_es
 * @property string|null $condicion_es
 * @property string|null $formulario_linea
 * @property TipoParametro|null $tipo
 * @property int $orden
 */
#[Fillable([
    'tax_year', 'categoria', 'clave', 'valor', 'grupo', 'nombre_es', 'condicion_es', 'formulario_linea',
    'tipo', 'orden', 'fuente_texto', 'fuente_url',
])]
class ParametroFiscal extends Model
{
    use TieneFuenteIrs;

    protected $table = 'parametros_fiscales';

    protected static function booted(): void
    {
        static::saved(function (ParametroFiscal $parametro) {
            ParametrosFiscales::invalidate();
            CatalogoFiscal::invalidar($parametro->tax_year);
        });
    }

    public function estaPendiente(): bool
    {
        return $this->valor === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'valor' => 'array',
            'grupo' => GrupoParametro::class,
            'tipo' => TipoParametro::class,
            'orden' => 'integer',
        ];
    }
}
