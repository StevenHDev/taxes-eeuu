<?php

namespace App\Http\Requests\CatalogoFiscal;

use App\Models\CatalogoFiscal\CatalogoAnio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Base de las validaciones del catálogo fiscal (F1.5): solo el
 * administrador edita (CatalogoFiscalPolicy) y el código solo se pone al
 * crear, nunca se cambia (otras piezas apuntan a él).
 */
abstract class PiezaCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', CatalogoAnio::class);
    }

    protected function anio(): int
    {
        return (int) $this->route('anio');
    }

    protected function creando(): bool
    {
        return $this->isMethod('post');
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasFuente(): array
    {
        return [
            'fuente_texto' => ['nullable', 'string', 'max:2000'],
            'fuente_url' => ['nullable', 'url:https,http', 'max:500'],
        ];
    }

    /**
     * Código: obligatorio y único en el año al crear; prohibido al editar.
     *
     * @return array<string, mixed>
     */
    protected function reglasCodigo(string $tabla, string $patron = '/^[A-Za-z0-9][A-Za-z0-9\-\.]*$/'): array
    {
        return [
            'codigo' => $this->creando()
                ? ['required', 'string', 'max:40', "regex:{$patron}", "unique:{$tabla},codigo,NULL,id,tax_year,{$this->anio()}"]
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'fuente_url' => 'enlace a la fuente del IRS',
            'fuente_texto' => 'fuente',
        ];
    }
}
