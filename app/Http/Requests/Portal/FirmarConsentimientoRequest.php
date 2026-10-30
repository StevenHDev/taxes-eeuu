<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Firma del consentimiento §7216 (F2.4): la casilla "Leí y acepto", el
 * nombre escrito como firma y la versión del texto que se mostró.
 */
class FirmarConsentimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->usaEntrevistaCatalogo() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'acepto' => ['accepted'],
            'nombre_escrito' => ['required', 'string', 'min:3', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'acepto.accepted' => __('Marca la casilla para continuar.'),
            'nombre_escrito.required' => __('Escribe tu nombre completo como firma.'),
        ];
    }
}
