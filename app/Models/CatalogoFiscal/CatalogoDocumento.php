<?php

namespace App\Models\CatalogoFiscal;

use App\Models\CatalogoFiscal\Concerns\EsDelCatalogoFiscal;
use App\Models\CatalogoFiscal\Concerns\TieneFuenteIrs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Documento que el cliente puede tener que entregar (pestaña 2, 4.0-A, 4.1 y
 * 5.2 del documento madre), con sus casillas del año. No confundir con
 * `App\Models\Documento`, que es el archivo que subió un cliente.
 *
 * @property int $id
 * @property string $codigo
 * @property string $grupo
 * @property string|null $nombre_oficial
 * @property string $nombre_sencillo_es
 * @property string|null $nombre_sencillo_en
 * @property string|null $emisor_es
 * @property string|null $emisor_en
 * @property string|null $fecha_llegada
 * @property string|null $si_no_llega_es
 * @property string|null $si_no_llega_en
 * @property array<int, string>|null $formatos
 * @property string $uno_por
 * @property int $orden
 */
#[Fillable([
    'tax_year', 'codigo', 'grupo', 'nombre_oficial', 'nombre_sencillo_es', 'nombre_sencillo_en',
    'emisor_es', 'emisor_en', 'fecha_llegada', 'si_no_llega_es', 'si_no_llega_en', 'formatos',
    'uno_por', 'siempre', 'orden', 'fuente_texto', 'fuente_url', 'activo',
])]
class CatalogoDocumento extends Model
{
    use EsDelCatalogoFiscal, TieneFuenteIrs;

    protected $table = 'catalogo_documentos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'formatos' => 'array',
            'siempre' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * @return HasMany<CatalogoCasilla, $this>
     */
    public function casillas(): HasMany
    {
        return $this->hasMany(CatalogoCasilla::class, 'documento_id')->orderBy('orden');
    }
}
