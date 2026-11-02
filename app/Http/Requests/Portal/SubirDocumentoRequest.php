<?php

namespace App\Http\Requests\Portal;

use App\Services\Entrevista\DocumentosEntrevista;

/**
 * Subir uno o varios archivos (hasta 10) a un ítem de la lista (F2.6, F3.3):
 * en los formatos del catálogo (en "otro", PDF, JPG, PNG o HEIC) y de hasta
 * 20 MB cada uno.
 */
class SubirDocumentoRequest extends ItemDocumentoRequest
{
    public const MAXIMO_KB = 20480;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->item();
        $extensiones = app(DocumentosEntrevista::class)->extensiones($this->entrevista()->tax_year, $item->documento_codigo);

        return [
            'archivos' => ['required', 'array', 'min:1', 'max:10'],
            'archivos.*' => ['file', 'max:'.self::MAXIMO_KB, 'extensions:'.implode(',', $extensiones)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::mensajes();
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'archivos.required' => __('Elige al menos un archivo.'),
            'archivos.*.max' => __('Cada archivo puede pesar hasta 20 MB.'),
            'archivos.*.extensions' => __('Formato no permitido. Usa :formatos.', ['formatos' => __('PDF, JPG, PNG o HEIC')]),
        ];
    }
}
