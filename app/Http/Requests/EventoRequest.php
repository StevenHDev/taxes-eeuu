<?php

namespace App\Http\Requests;

use App\Enums\ApiAbility;
use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\FieldMode;
use App\Enums\TaxForm;
use App\Support\TaxFieldCatalog;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Valida la forma estructural del evento (sección 3 de la especificación).
 * La validación semántica del contenido (SSN de 9 dígitos, fecha válida, etc.)
 * ocurre después, en EventoRecoleccionService, porque un evento con contenido
 * inválido igual se acepta y se persiste con estado "invalido" (regla 2 y 6),
 * no se rechaza con 422 — solo se rechaza si la FORMA del evento está mal.
 */
class EventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken && $token->can(ApiAbility::EventosWrite->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $modo = (string) $this->input('modo');
        $tipoDato = FieldDataType::tryFrom((string) $this->input('tipo_dato'));
        $esArray = in_array($tipoDato, [FieldDataType::ArrayString, FieldDataType::ArrayObject], true);

        return [
            'cliente_id' => ['nullable', 'integer', 'exists:users,id'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'forma' => ['required', Rule::enum(TaxForm::class)],
            'campo' => ['required', 'string'],
            'tipo_campo' => ['required', Rule::enum(FieldKind::class)],
            'modo' => ['required', Rule::enum(FieldMode::class)],
            'tipo_dato' => [
                Rule::requiredIf($modo === FieldMode::Texto->value),
                'nullable',
                Rule::enum(FieldDataType::class),
            ],
            // Los campos array_object/array_string pueden legítimamente llegar vacíos
            // (ej. "el cliente no tiene dependientes") — 'present' acepta un array
            // vacío, a diferencia de 'required', que lo rechaza.
            'contenido' => $modo === FieldMode::Texto->value
                ? ($esArray ? ['present', 'array'] : ['required'])
                : ['nullable'],
            // La extensión contra formatos_aceptados se valida en withValidator()
            // para poder dar un mensaje de error específico por campo.
            'file' => [
                Rule::requiredIf($modo === FieldMode::Archivo->value),
                'nullable',
                'file',
                'max:20480',
            ],
            'nombre_original' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(ValidatorContract|Validator $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $forma = TaxForm::tryFrom((string) $this->input('forma'));

            if (! $forma) {
                return;
            }

            $field = TaxFieldCatalog::find($forma->value, (string) $this->input('campo'));

            if (! $field) {
                $validator->errors()->add('campo', 'El campo indicado no existe en el catálogo para esa forma.');

                return;
            }

            /** @var FieldKind $tipoCampo */
            $tipoCampo = FieldKind::tryFrom((string) $this->input('tipo_campo'));

            if ($tipoCampo !== $field['tipo']) {
                $validator->errors()->add('tipo_campo', 'El tipo_campo no coincide con el catálogo maestro para este campo.');
            }

            $modo = FieldMode::tryFrom((string) $this->input('modo'));

            if ($field['tipo'] === FieldKind::Documento && $modo !== FieldMode::Archivo) {
                $validator->errors()->add('modo', 'Este campo solo admite modo "archivo".');
            }

            if ($field['tipo'] === FieldKind::Dato && $modo !== FieldMode::Texto) {
                $validator->errors()->add('modo', 'Este campo solo admite modo "texto".');
            }

            if ($modo === FieldMode::Archivo && $this->hasFile('file')) {
                $extension = strtolower((string) $this->file('file')->getClientOriginalExtension());
                $formatos = $field['formatos_aceptados'] ?? [];

                if ($formatos && ! \in_array($extension, $formatos, true)) {
                    $validator->errors()->add('file', 'Formato de archivo no aceptado para este campo. Formatos válidos: '.implode(', ', $formatos));
                }
            }
        });
    }
}
