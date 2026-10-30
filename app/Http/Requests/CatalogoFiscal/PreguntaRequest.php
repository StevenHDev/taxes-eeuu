<?php

namespace App\Http\Requests\CatalogoFiscal;

use App\Enums\CatalogoFiscal\NivelPregunta;
use App\Enums\CatalogoFiscal\TipoRespuesta;
use App\Rules\CondicionCatalogoValida;
use Illuminate\Validation\Rule;

class PreguntaRequest extends PiezaCatalogoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tipo = TipoRespuesta::tryFrom((string) $this->input('tipo_respuesta'));

        return [
            ...$this->reglasCodigo('catalogo_preguntas'),
            'bloque' => $this->creando() ? ['required', Rule::in(['A', 'B', 'C', 'D', 'E', 'F', 'G'])] : ['prohibited'],
            'pantalla' => ['nullable', 'integer', 'min:1', 'max:20'],
            'texto_es' => ['required', 'string', 'max:2000'],
            'texto_en' => ['nullable', 'string', 'max:2000'],
            'ayuda_es' => ['nullable', 'string', 'max:2000'],
            'ayuda_en' => ['nullable', 'string', 'max:2000'],
            'tipo_respuesta' => ['required', Rule::enum(TipoRespuesta::class)],
            'opciones' => $tipo?->llevaOpciones() ? ['required', 'array', 'min:2'] : ['nullable', 'array', 'max:0'],
            'opciones.*.valor' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'opciones.*.texto_es' => ['required', 'string', 'max:200'],
            'opciones.*.texto_en' => ['nullable', 'string', 'max:200'],
            'nivel' => ['required', Rule::enum(NivelPregunta::class)],
            'por_persona' => ['boolean'],
            'sensible' => ['boolean'],
            'condicion' => ['nullable', 'array', new CondicionCatalogoValida($this->anio())],
            'efecto_es' => ['nullable', 'string', 'max:2000'],
            ...$this->reglasFuente(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opciones.required' => 'Una pregunta de opciones necesita al menos dos opciones.',
            'opciones.min' => 'Una pregunta de opciones necesita al menos dos opciones.',
            'opciones.max' => 'Este tipo de respuesta no lleva opciones.',
            'opciones.*.valor.regex' => 'El valor de una opción va en minúsculas, sin espacios ni tildes (por ejemplo: casado).',
            'opciones.*.valor.distinct' => 'Hay dos opciones con el mismo valor.',
        ];
    }
}
