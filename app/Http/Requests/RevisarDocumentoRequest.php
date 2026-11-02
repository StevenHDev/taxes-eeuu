<?php

namespace App\Http\Requests;

use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El preparador revisa un documento del checklist de uno de sus clientes
 * (F3.4): validarlo, devolverlo con una observación o rechazar un "no
 * aplica". El texto es obligatorio para lo que vuelve al cliente.
 */
class RevisarDocumentoRequest extends FormRequest
{
    public const ACCIONES = ['validar', 'observar', 'rechazar_no_aplica'];

    public function authorize(): bool
    {
        /** @var User $cliente */
        $cliente = $this->route('cliente');

        return $this->item()->entrevista->user_id === $cliente->id
            && ($this->user()?->can('update', $cliente) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accion' => ['required', Rule::in(self::ACCIONES)],
            'texto' => ['nullable', 'required_unless:accion,validar', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.required_unless' => __('Escribe qué debe revisar el cliente.'),
        ];
    }

    public function item(): EntrevistaDocumento
    {
        /** @var EntrevistaDocumento $item */
        $item = $this->route('item');

        return $item;
    }
}
