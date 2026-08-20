<?php

namespace App\Http\Requests;

use App\Enums\ApiAbility;
use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\FieldMode;
use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
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

        $formasValidas = [...array_map(fn (TaxForm $f) => $f->value, TaxForm::cases()), ...CampoCatalogo::pseudoFormas()];

        return [
            // Además de las 10 formas, se aceptan las pseudo-formas (transversal,
            // documentos_extra): los campos únicos por cliente se guardan bajo esas.
            'forma' => ['required', Rule::in($formasValidas)],
            'tax_year' => ['required', 'integer', 'digits:4'],
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
                // Máximo 10 MB (en kilobytes). El frontend valida lo mismo antes de subir.
                'max:10240',
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
            'tax_year' => $this->query('tax_year'),
        ]);
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $forma = (string) $this->query('forma');

            if (! in_array($forma, CampoCatalogo::pseudoFormas(), true) && ! TaxForm::tryFrom($forma)) {
                return;
            }

            $field = TaxFieldCatalog::find((int) $this->query('tax_year'), $forma, (string) $this->route('campo'));

            if (! $field) {
                $validator->errors()->add('campo', 'El campo indicado no existe en el catálogo para esa forma.');

                return;
            }

            $modo = FieldMode::tryFrom((string) $this->input('modo'));

            if ($field['tipo'] === FieldKind::Documento && ! \in_array($modo, [FieldMode::Archivo, FieldMode::NoAplica], true)) {
                $validator->errors()->add('modo', 'Este campo solo admite modo "archivo" (o "no_aplica" si es opcional).');
            }

            if ($field['tipo'] === FieldKind::Dato && ! \in_array($modo, [FieldMode::Texto, FieldMode::NoAplica], true)) {
                $validator->errors()->add('modo', 'Este campo solo admite modo "texto" (o "no_aplica" si es opcional).');
            }

            // "no_aplica" es una respuesta del cliente ("no lo tengo"/"no aplica"),
            // no la ausencia de un valor obligatorio — solo tiene sentido en un
            // campo que de verdad puede faltar sin bloquear la forma.
            if ($modo === FieldMode::NoAplica && $field['obligatorio']) {
                $validator->errors()->add('modo', 'Este campo es obligatorio y no se puede marcar como "no_aplica".');
            }

            // Ver EventoRequest: sin este chequeo, un campo cuyo tipo_dato
            // cambió en el catálogo seguiría aceptando en silencio el shape
            // viejo, corrompiendo cualquier cálculo que dependa de ese dato.
            if ($modo === FieldMode::Texto && $field['tipo_dato'] !== null) {
                $tipoDatoEnviado = FieldDataType::tryFrom((string) $this->input('tipo_dato'));

                if ($tipoDatoEnviado !== $field['tipo_dato']) {
                    $validator->errors()->add('tipo_dato', 'El tipo_dato no coincide con el catálogo maestro para este campo.');
                }
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

    public function forma(): string
    {
        return (string) $this->query('forma');
    }

    public function taxYear(): int
    {
        return (int) $this->query('tax_year');
    }

    public function campoNombre(): string
    {
        return (string) $this->route('campo');
    }
}
