<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear la contraseña desde el enlace de activación de WhatsApp (F2.4). La
 * firma de la URL ya la revisa el middleware `signed`.
 */
class ActivarCuentaRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['password' => $this->passwordRules()];
    }
}
