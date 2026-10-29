<?php

namespace App\Models\CatalogoFiscal;

use App\Models\CatalogoFiscal\Concerns\EsDelCatalogoFiscal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Casilla de un documento en un año ("1", "1a", "Parte III col. A").
 * `clave` marca las que la lectura de documentos (F4) tiene que extraer.
 *
 * @property int $id
 * @property int $documento_id
 * @property string $casilla
 * @property string $descripcion
 * @property string|null $tipo_dato
 * @property bool $clave
 * @property int $orden
 */
#[Fillable(['tax_year', 'documento_id', 'casilla', 'descripcion', 'tipo_dato', 'clave', 'orden', 'activo'])]
class CatalogoCasilla extends Model
{
    use EsDelCatalogoFiscal;

    protected $table = 'catalogo_documento_casillas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clave' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogoDocumento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(CatalogoDocumento::class, 'documento_id');
    }
}
