<?php

namespace App\Models\CatalogoFiscal;

use App\Enums\CatalogoFiscal\FichaFormulario;
use App\Models\CatalogoFiscal\Concerns\EsDelCatalogoFiscal;
use App\Models\CatalogoFiscal\Concerns\TieneFuenteIrs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Formulario que se llena (pestaña 3, 4.0-B del documento madre). El 1040
 * es el único con `siempre = true`: es el formulario "front" al que llegan
 * los demás.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $para_que
 * @property bool $siempre
 * @property FichaFormulario $ficha
 * @property int $orden
 */
#[Fillable(['tax_year', 'codigo', 'nombre', 'para_que', 'siempre', 'ficha', 'orden', 'fuente_texto', 'fuente_url', 'activo'])]
class CatalogoFormulario extends Model
{
    use EsDelCatalogoFiscal, TieneFuenteIrs;

    protected $table = 'catalogo_formularios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'siempre' => 'boolean',
            'ficha' => FichaFormulario::class,
            'orden' => 'integer',
        ];
    }

    /**
     * @return HasMany<CatalogoLinea, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(CatalogoLinea::class, 'formulario_id')->orderBy('orden');
    }
}
