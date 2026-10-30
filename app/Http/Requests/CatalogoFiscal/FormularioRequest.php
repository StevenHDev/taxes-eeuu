<?php

namespace App\Http\Requests\CatalogoFiscal;

use App\Enums\CatalogoFiscal\FichaFormulario;
use App\Enums\CatalogoFiscal\OrigenLinea;
use Illuminate\Validation\Rule;

class FormularioRequest extends PiezaCatalogoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->reglasCodigo('catalogo_formularios', '/^[a-z0-9][a-z0-9\-]*$/'),
            'nombre' => ['required', 'string', 'max:255'],
            'para_que' => ['nullable', 'string', 'max:2000'],
            'ficha' => ['required', Rule::enum(FichaFormulario::class)],
            'lineas' => ['nullable', 'array'],
            'lineas.*.linea' => ['required', 'string', 'max:40', 'distinct'],
            'lineas.*.descripcion' => ['required', 'string', 'max:255'],
            'lineas.*.origen_tipo' => ['required', Rule::enum(OrigenLinea::class)],
            'lineas.*.origen_clave' => ['nullable', 'string', 'max:255', 'required_unless:lineas.*.origen_tipo,calculo'],
            ...$this->reglasFuente(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lineas.*.linea.distinct' => 'Hay dos líneas con el mismo número.',
            'lineas.*.origen_clave.required_unless' => 'Indica de dónde sale la línea (por ejemplo: w2:1), salvo que se calcule.',
        ];
    }
}
