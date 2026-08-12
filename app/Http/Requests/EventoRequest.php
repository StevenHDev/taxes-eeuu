<?php

namespace App\Http\Requests;

use App\Enums\ApiAbility;
use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\FieldMode;
use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
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
            // Solo se usa cuando cliente_id es null: identifica/crea al cliente por
            // teléfono en vez de (o además de) external_ref — ver resolverCliente().
            'phone' => ['nullable', 'string', 'max:32'],
            // Las 10 formas del IRS, o 'transversal' para los datos únicos por
            // cliente (SSN, cónyuge, dependientes, W-2, 1099-NEC, declaración
            // anterior) — que no pertenecen a una forma en particular.
            'forma' => ['required', Rule::in([...array_map(fn (TaxForm $f) => $f->value, TaxForm::cases()), CampoCatalogo::TRANSVERSAL])],
            // Sin default: el agente conversacional externo siempre debe declarar
            // explícitamente para qué año fiscal es el dato (nunca se asume).
            'tax_year' => ['required', 'integer', 'digits:4'],
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
            // Cuando un campo (o un subcampo suyo) puede ser revelado por más de
            // un documento (ver clave `revela.acumulable` de consultar_pendientes_cliente,
            // ej. intereses_dividendos por 1099-INT Y 1099-DIV), el agente marca
            // `acumular: true` para que el backend SUME el nuevo valor al ya
            // guardado en vez de sobrescribirlo — ver EventoRecoleccionService::procesar().
            // No 'boolean' nativo: en esta API todos los parámetros viajan como
            // string (ver punto 8 de guardar_campo_cliente en docs/prompt.md), y
            // la regla 'boolean' de Laravel rechaza el texto "true"/"false" —
            // solo acepta true/false/0/1/"0"/"1".
            'acumular' => ['sometimes', $this->boolLikeRule()],
            // Solo se usa junto con acumular=true sobre un campo tipo_dato=object:
            // indica qué subcampo del objeto es el que este documento contribuye
            // (los demás subcampos del `contenido` se guardan tal como llegan).
            'subcampo' => ['nullable', 'string'],
            // Un documento con `revela` no vacío puede resolver, en la MISMA
            // llamada, cada campo que revela — en vez de que el agente tenga que
            // decidir invocar la tool de nuevo por cada uno (encontrado en
            // producción: con modelos más chicos esa segunda invocación no
            // siempre ocurre, aunque el prompt y la respuesta ya la indiquen con
            // claridad — ver `revela` en la respuesta de este mismo endpoint).
            // Cada item es siempre modo="texto" implícito (un revelado nunca es
            // otro documento ni un "no_aplica") — ver DEFINICIÓN DE LAS TOOLS.
            'revelados' => ['sometimes', 'array'],
            'revelados.*.forma' => ['required', Rule::in([...array_map(fn (TaxForm $f) => $f->value, TaxForm::cases()), CampoCatalogo::TRANSVERSAL])],
            'revelados.*.campo' => ['required', 'string'],
            'revelados.*.tipo_campo' => ['required', Rule::enum(FieldKind::class)],
            'revelados.*.tipo_dato' => ['required', Rule::enum(FieldDataType::class)],
            // 'present' (no 'required'): un array_string/array_object revelado
            // puede legítimamente llegar vacío, igual que el campo raíz.
            'revelados.*.contenido' => ['present'],
            'revelados.*.subcampo' => ['nullable', 'string'],
            'revelados.*.acumular' => ['sometimes', $this->boolLikeRule()],
        ];
    }

    /**
     * El agente envía `acumular` como texto "true"/"false" (todos los
     * parámetros de esta API viajan como string) — la regla nativa `boolean`
     * de Laravel no acepta esos textos, solo true/false/0/1/"0"/"1" (ver
     * `Illuminate\Validation\Concerns\ValidatesAttributes::validateBoolean()`).
     */
    private function boolLikeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (! in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                $fail('El campo :attribute debe ser verdadero (true) o falso (false).');
            }
        };
    }

    public function withValidator(ValidatorContract|Validator $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $taxYear = (int) $this->input('tax_year');
            $forma = (string) $this->input('forma');

            if ($forma !== CampoCatalogo::TRANSVERSAL && ! TaxForm::tryFrom($forma)) {
                return;
            }

            $field = TaxFieldCatalog::find($taxYear, $forma, (string) $this->input('campo'));

            if (! $field) {
                $validator->errors()->add('campo', 'El campo indicado no existe en el catálogo para esa forma.');

                return;
            }

            $modo = FieldMode::tryFrom((string) $this->input('modo'));

            $this->validarCoincidenciaCatalogo(
                validator: $validator,
                prefijo: '',
                field: $field,
                tipoCampoInput: (string) $this->input('tipo_campo'),
                tipoDatoInput: $this->input('tipo_dato'),
                // Sin este chequeo, un campo cuyo tipo_dato cambió en el catálogo
                // (ej. ingresos: number -> object) seguiría aceptando en silencio
                // el tipo_dato viejo de una integración desactualizada, corrompiendo
                // cualquier cálculo que dependa de ese dato (ver AgiCalculator).
                // Solo aplica en modo="texto" — un modo="archivo"/"no_aplica" no
                // manda tipo_dato, o manda uno que no describe el contenido real.
                verificarTipoDato: $modo === FieldMode::Texto,
                acumular: $this->boolean('acumular'),
                subcampo: $this->input('subcampo'),
            );

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

            if ($modo === FieldMode::Archivo && $this->hasFile('file')) {
                $extension = strtolower((string) $this->file('file')->getClientOriginalExtension());
                $formatos = $field['formatos_aceptados'] ?? [];

                if ($formatos && ! \in_array($extension, $formatos, true)) {
                    $validator->errors()->add('file', 'Formato de archivo no aceptado para este campo. Formatos válidos: '.implode(', ', $formatos));
                }
            }

            $this->validarRevelados($validator, $taxYear);
        });
    }

    /**
     * Cada item de `revelados` es siempre un campo tipo dato resuelto por texto
     * (nunca un documento, nunca "no_aplica") — ver DEFINICIÓN DE LAS TOOLS,
     * parámetro `revelados`.
     */
    private function validarRevelados(ValidatorContract $validator, int $taxYear): void
    {
        foreach ((array) $this->input('revelados', []) as $i => $item) {
            $prefijo = "revelados.{$i}.";
            $forma = (string) ($item['forma'] ?? '');
            $campo = (string) ($item['campo'] ?? '');

            if ($forma !== CampoCatalogo::TRANSVERSAL && ! TaxForm::tryFrom($forma)) {
                continue;
            }

            $field = TaxFieldCatalog::find($taxYear, $forma, $campo);

            if (! $field) {
                $validator->errors()->add("{$prefijo}campo", 'El campo indicado no existe en el catálogo para esa forma.');

                continue;
            }

            if ($field['tipo'] === FieldKind::Documento) {
                $validator->errors()->add("{$prefijo}campo", 'Un campo revelado no puede ser de tipo documento.');

                continue;
            }

            $this->validarCoincidenciaCatalogo(
                validator: $validator,
                prefijo: $prefijo,
                field: $field,
                tipoCampoInput: (string) ($item['tipo_campo'] ?? ''),
                tipoDatoInput: $item['tipo_dato'] ?? null,
                verificarTipoDato: true,
                // No (bool) directo: `acumular` viaja como string ("true"/"false")
                // y (bool) "false" da true en PHP por ser un string no vacío.
                acumular: filter_var($item['acumular'] ?? false, FILTER_VALIDATE_BOOLEAN),
                subcampo: $item['subcampo'] ?? null,
            );
        }
    }

    /**
     * Coincidencia tipo_campo/tipo_dato contra el catálogo maestro, y
     * consistencia acumular/subcampo — compartido entre el campo raíz
     * (`withValidator()`) y cada item de `revelados` (`validarRevelados()`).
     *
     * @param  array<string, mixed>  $field
     */
    private function validarCoincidenciaCatalogo(
        ValidatorContract $validator,
        string $prefijo,
        array $field,
        string $tipoCampoInput,
        mixed $tipoDatoInput,
        bool $verificarTipoDato,
        bool $acumular,
        mixed $subcampo,
    ): void {
        $tipoCampo = FieldKind::tryFrom($tipoCampoInput);

        if ($tipoCampo !== $field['tipo']) {
            $validator->errors()->add("{$prefijo}tipo_campo", 'El tipo_campo no coincide con el catálogo maestro para este campo.');
        }

        $tipoDatoEnviado = FieldDataType::tryFrom((string) $tipoDatoInput);

        if ($verificarTipoDato && $field['tipo_dato'] !== null && $tipoDatoEnviado !== $field['tipo_dato']) {
            $validator->errors()->add("{$prefijo}tipo_dato", 'El tipo_dato no coincide con el catálogo maestro para este campo.');
        }

        if (! $acumular) {
            return;
        }

        if ($tipoDatoEnviado === FieldDataType::Number) {
            if ($subcampo !== null) {
                $validator->errors()->add("{$prefijo}subcampo", 'No se especifica subcampo cuando el campo acumulable es numérico simple.');
            }
        } elseif ($tipoDatoEnviado === FieldDataType::Object) {
            $subcampos = $field['subcampos'] ?? [];

            if (! is_string($subcampo) || $subcampo === '') {
                $validator->errors()->add("{$prefijo}subcampo", 'Se requiere indicar el subcampo a acumular para un campo tipo objeto.');
            } elseif (! \in_array($subcampo, $subcampos, true)) {
                $validator->errors()->add("{$prefijo}subcampo", 'El subcampo indicado no existe en el catálogo para este campo.');
            }
        } else {
            $validator->errors()->add("{$prefijo}acumular", 'acumular solo aplica a campos numéricos o a un subcampo de un campo tipo objeto.');
        }
    }
}
