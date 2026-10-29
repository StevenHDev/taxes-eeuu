<?php

namespace App\Models\CatalogoFiscal;

use App\Enums\CatalogoFiscal\NivelPregunta;
use App\Enums\CatalogoFiscal\TipoRespuesta;
use App\Models\CatalogoFiscal\Concerns\EsDelCatalogoFiscal;
use App\Models\CatalogoFiscal\Concerns\TieneFuenteIrs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pregunta de la entrevista (pestaña 1, sección 3 del documento madre,
 * basada en el 13614-C). El año va en el texto como `{anio}` y se reemplaza
 * al mostrarlo, para que copiar un año no obligue a reescribir los textos.
 *
 * @property int $id
 * @property string $codigo
 * @property string $bloque
 * @property int|null $pantalla
 * @property int $orden
 * @property string $texto_es
 * @property string|null $texto_en
 * @property string|null $ayuda_es
 * @property string|null $ayuda_en
 * @property TipoRespuesta $tipo_respuesta
 * @property array<int, mixed>|null $opciones
 * @property NivelPregunta $nivel
 * @property bool $por_persona
 * @property bool $sensible
 * @property array<string, mixed>|null $condicion
 */
#[Fillable([
    'tax_year', 'codigo', 'bloque', 'pantalla', 'orden', 'texto_es', 'texto_en', 'ayuda_es', 'ayuda_en',
    'tipo_respuesta', 'opciones', 'nivel', 'por_persona', 'sensible', 'condicion',
    'fuente_texto', 'fuente_url', 'activo',
])]
class CatalogoPregunta extends Model
{
    use EsDelCatalogoFiscal, TieneFuenteIrs;

    protected $table = 'catalogo_preguntas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pantalla' => 'integer',
            'orden' => 'integer',
            'tipo_respuesta' => TipoRespuesta::class,
            'opciones' => 'array',
            'nivel' => NivelPregunta::class,
            'por_persona' => 'boolean',
            'sensible' => 'boolean',
            'condicion' => 'array',
        ];
    }

    /**
     * @return HasMany<CatalogoActivacion, $this>
     */
    public function activaciones(): HasMany
    {
        return $this->hasMany(CatalogoActivacion::class, 'pregunta_id');
    }

    public function texto(string $idioma = 'es'): string
    {
        $texto = $idioma === 'en' && filled($this->texto_en) ? $this->texto_en : $this->texto_es;

        return str_replace('{anio}', (string) $this->tax_year, $texto);
    }
}
