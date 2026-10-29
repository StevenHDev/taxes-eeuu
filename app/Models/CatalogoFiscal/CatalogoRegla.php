<?php

namespace App\Models\CatalogoFiscal;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Models\CatalogoFiscal\Concerns\EsDelCatalogoFiscal;
use App\Models\CatalogoFiscal\Concerns\TieneFuenteIrs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Regla de validación (pestaña 5, 6.2 del documento madre). `implementada`
 * queda en falso hasta que la fase 4 la ejecute.
 *
 * @property int $id
 * @property string $codigo
 * @property string $descripcion
 * @property SeveridadRegla $severidad
 * @property bool $implementada
 * @property int $orden
 */
#[Fillable(['tax_year', 'codigo', 'descripcion', 'severidad', 'implementada', 'orden', 'fuente_texto', 'fuente_url', 'activo'])]
class CatalogoRegla extends Model
{
    use EsDelCatalogoFiscal, TieneFuenteIrs;

    protected $table = 'catalogo_reglas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severidad' => SeveridadRegla::class,
            'implementada' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
