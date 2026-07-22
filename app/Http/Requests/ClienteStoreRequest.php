<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta rápida de un cliente desde /clientes. A diferencia de UsuarioRequest,
 * el rol queda fijo en `client` — un preparador puede usar esto (se asigna a
 * sí mismo), pero nunca puede elegir un rol distinto ni crear un usuario
 * de administración por esta vía.
 */
class ClienteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createCliente', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')],
            'preparer_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::Preparer->value),
            ],
        ];
    }
}
