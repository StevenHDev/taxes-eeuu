<?php

namespace App\Http\Requests;

use App\Enums\ApiAbility;
use App\Enums\TaxForm;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Declara las formas aplicables de un cliente (POST /api/clientes/{cliente}/formas)
 * — lo invoca el agente conversacional externo apenas resuelve, en lenguaje
 * natural, el árbol de determinación de forma(s) (docs/prompt.md, PASO A-D).
 * `tax_year` nunca tiene default de config aquí: es una acción mutante del
 * agente, igual que `guardar_campo_cliente`/`marcarRevisado`.
 */
class ClienteFormasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cliente = $this->routeCliente();

        return $cliente !== null && $this->user()->can('update', $cliente) && $this->tokenHasWriteAbility();
    }

    protected function tokenHasWriteAbility(): bool
    {
        $token = $this->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return true;
        }

        return $token->can(ApiAbility::ClientesWrite->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_year' => ['required', 'integer', 'digits:4'],
            'formas' => ['required', 'array', 'min:1'],
            'formas.*' => ['required', 'distinct', Rule::in(array_map(fn (TaxForm $f) => $f->value, TaxForm::cases()))],
        ];
    }

    protected function routeCliente(): ?User
    {
        $cliente = $this->route('cliente');

        return $cliente instanceof User ? $cliente : null;
    }
}
