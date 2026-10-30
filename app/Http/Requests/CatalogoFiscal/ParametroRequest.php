<?php

namespace App\Http\Requests\CatalogoFiscal;

use App\Enums\CatalogoFiscal\TipoParametro;
use App\Models\ParametroFiscal;

/**
 * Los parámetros no se crean desde la pantalla: las calculadoras los leen
 * por su clave, así que uno nuevo no tendría efecto. Se edita el valor (o se
 * deja pendiente) y su descripción.
 */
class ParametroRequest extends PiezaCatalogoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ParametroFiscal $parametro */
        $parametro = $this->route('parametro');

        $valor = match ($parametro->tipo) {
            TipoParametro::Monto, TipoParametro::Numero => ['nullable', 'numeric', 'min:0'],
            TipoParametro::Porcentaje => ['nullable', 'numeric', 'min:0', 'max:1'],
            TipoParametro::Fecha => ['nullable', 'date_format:Y-m-d'],
            TipoParametro::Tabla => ['nullable', 'array', 'min:1'],
            default => ['nullable'],
        };

        return [
            'valor' => $valor,
            'valor.*.desde' => $parametro->tipo === TipoParametro::Tabla ? ['required', 'numeric', 'min:0'] : [],
            'valor.*.tasa' => $parametro->tipo === TipoParametro::Tabla ? ['required', 'numeric', 'min:0', 'max:1'] : [],
            'nombre_es' => ['required', 'string', 'max:255'],
            'condicion_es' => ['nullable', 'string', 'max:2000'],
            'formulario_linea' => ['nullable', 'string', 'max:255'],
            ...$this->reglasFuente(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valor.max' => 'Un porcentaje va entre 0 y 1 (por ejemplo: 0.35 para 35 %).',
        ];
    }
}
