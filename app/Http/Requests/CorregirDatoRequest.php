<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El preparador corrige un dato leído de un documento de uno de sus
 * clientes en el visor (F4.6). Que el dato sea del ítem y del cliente de la
 * URL lo comprueba el controlador (404).
 */
class CorregirDatoRequest extends FormRequest
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
            'valor' => ['nullable', 'string', 'max:255'],
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * El nombre del campo en los mensajes de error (sin esto salía "motive").
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'valor' => __('valor'),
            'motivo' => __('motivo de la corrección'),
        ];
    }
}
