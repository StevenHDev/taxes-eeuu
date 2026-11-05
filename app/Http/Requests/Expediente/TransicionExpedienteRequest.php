<?php

namespace App\Http\Requests\Expediente;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El preparador mueve el expediente por los estados manuales (F5.3a). La
 * fecha es obligatoria al presentar y el motivo al marcar el rechazo del IRS.
 */
class TransicionExpedienteRequest extends FormRequest
{
    public const ACCIONES = ['empezar', 'preparado', 'a_firma', 'presentado', 'aceptado', 'rechazado', 'volver_a_revision'];

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
            'accion' => ['required', Rule::in(self::ACCIONES)],
            'fecha' => ['required_if:accion,presentado', 'nullable', 'date', 'before_or_equal:today'],
            'motivo' => ['required_if:accion,rechazado', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required_if' => __('Escribe la fecha en que se presentó la declaración.'),
            'fecha.before_or_equal' => __('La fecha de presentación no puede ser futura.'),
            'motivo.required_if' => __('Escribe el motivo del rechazo.'),
        ];
    }
}
