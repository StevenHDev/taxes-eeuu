<?php

namespace App\Http\Requests\Portal;

/**
 * "No aplica" en un documento de la lista, con el motivo obligatorio (F3.3).
 */
class MarcarNoAplicaRequest extends ItemDocumentoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => __('Cuéntanos por qué no aplica.'),
            'motivo.max' => __('El motivo puede tener hasta 500 caracteres.'),
        ];
    }
}
