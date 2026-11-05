<?php

namespace App\Http\Requests\Expediente;

use App\Enums\Expediente\ClasificacionPersona;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El preparador clasifica a una persona del bloque C, con motivo (F5.3a).
 */
class ClasificarPersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $cliente */
        $cliente = $this->route('cliente');

        return $this->user()?->can('update', $cliente) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_year' => ['required', 'integer', 'digits:4'],
            'clasificacion' => ['required', Rule::enum(ClasificacionPersona::class)],
            'motivo' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['motivo.required' => __('Escribe el motivo de tu decisión.')];
    }
}
