<?php

namespace App\Http\Requests\Expediente;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El preparador decide el estado civil para el IRS, con motivo (F5.3a).
 */
class EstadoCivilRequest extends FormRequest
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
            'valor' => ['required', Rule::enum(EstadoCivilIrs::class)],
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
