<?php

namespace App\Http\Requests\CatalogoFiscal;

use Illuminate\Validation\Rule;

class DocumentoRequest extends PiezaCatalogoRequest
{
    public const GRUPOS = ['identidad', 'trabajo', 'negocio', 'jubilacion', 'inversiones', 'otros_ingresos', 'gastos', 'situaciones', 'pagos'];

    public const UNO_POR = ['unico', 'empleo', 'poliza', 'persona', 'fuente'];

    public const FORMATOS = ['pdf', 'jpg', 'png', 'heic', 'xlsx', 'csv'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->reglasCodigo('catalogo_documentos', '/^[a-z0-9][a-z0-9\-]*$/'),
            'grupo' => ['required', Rule::in(self::GRUPOS)],
            'nombre_sencillo_es' => ['required', 'string', 'max:255'],
            'nombre_sencillo_en' => ['nullable', 'string', 'max:255'],
            'nombre_oficial' => ['nullable', 'string', 'max:255'],
            'emisor_es' => ['nullable', 'string', 'max:255'],
            'emisor_en' => ['nullable', 'string', 'max:255'],
            'fecha_llegada' => ['nullable', 'date_format:Y-m-d'],
            'si_no_llega_es' => ['nullable', 'string', 'max:2000'],
            'si_no_llega_en' => ['nullable', 'string', 'max:2000'],
            'formatos' => ['nullable', 'array'],
            'formatos.*' => [Rule::in(self::FORMATOS), 'distinct'],
            'uno_por' => ['required', Rule::in(self::UNO_POR)],
            'casillas' => ['nullable', 'array'],
            'casillas.*.casilla' => ['required', 'string', 'max:40', 'distinct'],
            'casillas.*.descripcion' => ['required', 'string', 'max:255'],
            'casillas.*.tipo_dato' => ['nullable', 'string', 'max:40'],
            'casillas.*.clave' => ['boolean'],
            ...$this->reglasFuente(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código va en minúsculas con guiones (por ejemplo: 1099-int).',
            'casillas.*.casilla.distinct' => 'Hay dos casillas con el mismo número.',
        ];
    }
}
