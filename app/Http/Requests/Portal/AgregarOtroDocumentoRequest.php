<?php

namespace App\Http\Requests\Portal;

use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Validation\Validator;

/**
 * "Agregar otro" del mismo documento (otro banco, otro empleo, otra póliza):
 * solo los que el catálogo dice que van por fuente, empleo o póliza (F3.3).
 */
class AgregarOtroDocumentoRequest extends ItemDocumentoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $item = $this->item();
            $documento = app(CatalogoFiscal::class)->documentos($this->entrevista()->tax_year)->firstWhere('codigo', $item->documento_codigo);

            if (! in_array($documento['uno_por'] ?? null, ['fuente', 'empleo', 'poliza'], true)) {
                $validator->errors()->add('agregar', __('De este documento solo hace falta uno.'));
            }
        }];
    }
}
