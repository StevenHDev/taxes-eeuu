<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta/edición de un usuario desde el panel de administración (/usuarios).
 * Distinto del alta rápida de cliente en /clientes (ver UsuarioController::store
 * vs ClienteController::store) — acá se puede elegir cualquier rol.
 */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->routeUsuario();

        return $usuario
            ? $this->user()->can('updateProfile', $usuario)
            : $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuario = $this->routeUsuario();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($usuario?->id),
            ],
            'phone' => [
                'nullable', 'string', 'max:32',
                Rule::unique('users', 'phone')->ignore($usuario?->id),
            ],
            'password' => [
                Rule::requiredIf($usuario === null),
                'nullable', 'string', 'min:8',
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'preparer_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::Preparer->value),
            ],
        ];
    }

    protected function routeUsuario(): ?User
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User ? $usuario : null;
    }
}
