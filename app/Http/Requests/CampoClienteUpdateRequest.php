<?php

namespace App\Http\Requests;

use App\Enums\ApiAbility;
use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\FieldMode;
use App\Enums\TaxForm;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Corrección manual de un campo por un preparador/administrador (PATCH /clientes/{cliente}/campos/{campo}).
 * Se usa tanto desde el panel web (sesión) como desde la API (token con ability clientes:write).
 * `forma` viaja como query param porque el nombre de campo se repite entre formas
 * (ej. "gastos" en form_1065/form_1120/form_1120_s/form_1041/form_990).
 */
class CampoClienteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cliente = $this->routeCliente();

        return $cliente !== null && $this->user()->can('update', $cliente) && $this->tokenHasWriteAbility();
    }

    protected function tokenHasWriteAbility(): bool
    {
        $token = $this->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return true;
        }

        return $token->can(ApiAbility::ClientesWrite->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $modo = (string) $this->input('modo');
        $tipoDato = FieldDataType::tryFrom((string) $this->input('tipo_dato'));
        $esArray = in_array($tipoDato, [FieldDataType::ArrayString, FieldDataType::ArrayObject], true);

        return [
            'forma' => ['required', Rule::enum(TaxForm::class)],
            'modo' => ['required', Rule::enum(FieldMode::class)],
            'tipo_dato' => [
                Rule::requiredIf($modo === FieldMode::Texto->value),
                'nullable',
                Rule::enum(FieldDataType::class),
            ],
            // Ver EventoRequest: los array_object/array_string pueden llegar vacíos.
            'contenido' => $modo === FieldMode::Texto->value
                ? ($esArray ? ['present', 'array'] : ['required'])
                : ['nullable'],
            'file' => [
                Rule::requiredIf($modo === FieldMode::Archivo->value),
                'nullable',
                'file',
                'max:20480',
            ],
            'nombre_original' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * `forma` llega por query string (?forma=...), no por el body — se inyecta aquí
     * para que participe de las reglas de validación estándar.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'forma' => $this->query('forma'),
        ]);
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $forma = TaxForm::tryFrom((string) $this->query('forma'));

            if (! $forma) {
                return;
            }

            $field = TaxFieldCatalog::find($forma->value, (string) $this->route('campo'));

            if (! $field) {
                $validator->errors()->add('campo', 'El campo indicado no existe en el catálogo para esa forma.');

                return;
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

    protected function routeCliente(): ?User
    {
        $cliente = $this->route('cliente');

        return $cliente instanceof User ? $cliente : null;
    }

    public function forma(): TaxForm
    {
        return TaxForm::from((string) $this->query('forma'));
    }

    public function campoNombre(): string
    {
        return (string) $this->route('campo');
    }
}
