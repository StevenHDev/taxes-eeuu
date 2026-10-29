<?php

namespace App\Models\CatalogoFiscal;

use App\Enums\CatalogoFiscal\OrigenLinea;
use App\Models\CatalogoFiscal\Concerns\EsDelCatalogoFiscal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de un formulario y de dónde sale su valor (7.3): una casilla de un
 * documento ("w2:1"), otra línea ("sch-c:31"), una respuesta ("B1") o un
 * cálculo. `campo_pdf` se llena en la fase 6.
 *
 * @property int $id
 * @property int $formulario_id
 * @property string $linea
 * @property string $descripcion
 * @property OrigenLinea $origen_tipo
 * @property string|null $origen_clave
 * @property string|null $campo_pdf
 * @property int $orden
 */
#[Fillable(['tax_year', 'formulario_id', 'linea', 'descripcion', 'origen_tipo', 'origen_clave', 'campo_pdf', 'orden', 'activo'])]
class CatalogoLinea extends Model
{
    use EsDelCatalogoFiscal;

    protected $table = 'catalogo_lineas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origen_tipo' => OrigenLinea::class,
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogoFormulario, $this>
     */
    public function formulario(): BelongsTo
    {
        return $this->belongsTo(CatalogoFormulario::class, 'formulario_id');
    }
}
