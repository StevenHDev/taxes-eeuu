<?php

namespace App\Http\Requests\Portal;

/**
 * La nota del cliente en un documento de registros propios (F3.7): lo que
 * los papeles no dicen. Vacía la borra.
 */
class NotaDocumentoRequest extends ItemDocumentoRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nota' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
