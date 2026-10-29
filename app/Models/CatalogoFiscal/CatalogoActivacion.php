<?php

namespace App\Models\CatalogoFiscal;

use App\Enums\CatalogoFiscal\ObjetivoActivacion;
use App\Models\CatalogoFiscal\Concerns\EsDelCatalogoFiscal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Columna "Activa / descarta" de la sección 3: qué respuesta de una pregunta
 * abre otra pregunta, un documento o un formulario. El objetivo se guarda
 * por código del mismo año. En esta fase solo se guarda; la entrevista de la
 * fase 2 es la que la evalúa.
 *
 * @property int $id
 * @property int $pregunta_id
 * @property mixed $respuesta
 * @property ObjetivoActivacion $objetivo_tipo
 * @property string $objetivo_codigo
 * @property string|null $nota
 */
#[Fillable(['tax_year', 'pregunta_id', 'respuesta', 'objetivo_tipo', 'objetivo_codigo', 'nota', 'activo'])]
class CatalogoActivacion extends Model
{
    use EsDelCatalogoFiscal;

    protected $table = 'catalogo_activaciones';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'respuesta' => 'json',
            'objetivo_tipo' => ObjetivoActivacion::class,
        ];
    }

    /**
     * @return BelongsTo<CatalogoPregunta, $this>
     */
    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(CatalogoPregunta::class, 'pregunta_id');
    }
}
