<?php

namespace App\Http\Requests;

use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta/edición de una definición del catálogo (qué campos pide cada forma).
 * Compartida entre store/update — en update, `clave` puede repetirse consigo
 * misma (se excluye el registro actual de la validación de unicidad).
 */
class CatalogoCampoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campo = $this->route('campo');

        return $campo instanceof CampoCatalogo
            ? $this->user()->can('update', CampoCatalogo::class)
            : $this->user()->can('create', CampoCatalogo::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tipoCampo = (string) $this->input('tipo_campo');
        $formasValidas = [...array_map(fn (TaxForm $f) => $f->value, TaxForm::cases()), CampoCatalogo::TRANSVERSAL];
        $campoActual = $this->route('campo');

        return [
            'forma' => ['required', Rule::in($formasValidas)],
            'clave' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('catalogo_campos', 'clave')
                    ->where('forma', $this->input('forma'))
                    ->ignore($campoActual instanceof CampoCatalogo ? $campoActual->id : null),
            ],
            'tipo_campo' => ['required', Rule::enum(FieldKind::class)],
            'tipo_dato' => [
                Rule::requiredIf($tipoCampo !== FieldKind::Documento->value),
                Rule::prohibitedIf($tipoCampo === FieldKind::Documento->value),
                'nullable',
                Rule::enum(FieldDataType::class),
            ],
            'formatos_aceptados' => [
                Rule::requiredIf(in_array($tipoCampo, [FieldKind::Documento->value, FieldKind::Mixto->value], true)),
                Rule::prohibitedIf($tipoCampo === FieldKind::Dato->value),
                'nullable',
                'array',
            ],
            'formatos_aceptados.*' => ['string', 'regex:/^[a-z0-9]+$/'],
            'subcampos' => ['nullable', 'array'],
            'subcampos.*' => ['string'],
            'obligatorio' => ['required', 'boolean'],
            'sensible' => ['required', 'boolean'],
        ];
    }
}
