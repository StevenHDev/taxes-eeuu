<?php

namespace App\Http\Requests\CatalogoFiscal;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use Illuminate\Validation\Rule;

class ReglaRequest extends PiezaCatalogoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->reglasCodigo('catalogo_reglas', '/^R-\d{2,3}$/'),
            'descripcion' => ['required', 'string', 'max:2000'],
            'severidad' => ['required', Rule::enum(SeveridadRegla::class)],
            ...$this->reglasFuente(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['codigo.regex' => 'El código de una regla es R- y dos o tres números (por ejemplo: R-14).'];
    }
}
