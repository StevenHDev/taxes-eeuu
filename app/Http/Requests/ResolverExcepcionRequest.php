<?php

namespace App\Http\Requests;

use App\Models\Lectura\Excepcion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El preparador resuelve una excepción de uno de sus clientes (F4.7a):
 * aceptarla con una nota o consultarle al cliente. El texto es obligatorio
 * en ambos casos.
 */
class ResolverExcepcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $cliente */
        $cliente = $this->excepcion()->entrevista->user;

        return $this->user()?->can('update', $cliente) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [$this->campo() => ['required', 'string', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nota.required' => __('Escribe por qué aceptas esta excepción.'),
            'texto.required' => __('Escribe qué le quieres preguntar al cliente.'),
        ];
    }

    /**
     * Aceptar lleva una nota; consultar, el texto para el cliente.
     */
    public function campo(): string
    {
        return $this->routeIs('clientes.excepciones.aceptar') ? 'nota' : 'texto';
    }

    public function excepcion(): Excepcion
    {
        /** @var Excepcion $excepcion */
        $excepcion = $this->route('excepcion');

        return $excepcion;
    }
}
