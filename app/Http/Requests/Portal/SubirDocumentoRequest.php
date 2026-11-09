<?php

namespace App\Http\Requests\Portal;

use App\Services\Entrevista\DocumentosEntrevista;

/**
 * Subir uno o varios archivos (hasta 10) a un ítem de la lista (F2.6, F3.3):
 * en los formatos del catálogo (en "otro", PDF, JPG, PNG o HEIC) y de hasta
 * 20 MB cada uno. El mensaje de formato no permitido nombra los formatos de
 * ese documento, no una lista fija.
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
        $item = $this->item();

        return self::mensajes(app(DocumentosEntrevista::class)->extensiones($this->entrevista()->tax_year, $item->documento_codigo));
    }

    /**
     * @param  list<string>|null  $extensiones  los formatos del documento; sin ellos, los de siempre
     * @return array<string, string>
     */
    public static function mensajes(?array $extensiones = null): array
    {
        return [
            'archivos.required' => __('Elige al menos un archivo.'),
            'archivos.*.max' => __('Cada archivo puede pesar hasta 20 MB.'),
            'archivos.*.extensions' => __('Formato no permitido. Usa :formatos.', ['formatos' => self::formatos($extensiones)]),
        ];
    }

    /**
     * Los formatos como los lee una persona: "PDF, JPG, PNG o HEIC". `jpeg` no se nombra aparte de `jpg`.
     *
     * @param  list<string>|null  $extensiones
     */
    private static function formatos(?array $extensiones): string
    {
        $formatos = array_values(array_unique(array_map(strtoupper(...), array_filter($extensiones ?? [], fn (string $e) => $e !== 'jpeg'))));

        if ($formatos === [] || $formatos === ['PDF', 'JPG', 'PNG', 'HEIC']) {
            return __('PDF, JPG, PNG o HEIC');
        }

        $ultimo = array_pop($formatos);

        return $formatos === [] ? $ultimo : implode(', ', $formatos).' '.(str_starts_with(app()->getLocale(), 'en') ? 'or' : 'o').' '.$ultimo;
    }
}
