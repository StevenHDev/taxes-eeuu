<?php

namespace App\Http\Requests\Expediente;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El preparador confirma (o desconfirma) un punto manual del checklist (F5.3a).
 */
class PuntoRevisionRequest extends FormRequest
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
            'confirmado' => ['required', 'boolean'],
            'nota' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
