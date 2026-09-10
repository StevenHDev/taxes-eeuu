<?php

namespace App\Http\Requests;

use App\Enums\ApiAbility;
use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\FieldMode;
use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use App\Support\EventoValidator;
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
     * Cuando el evento llega como multipart/form-data (necesario para adjuntar
     * `file`), el cliente HTTP del agente serializa como string JSON tanto
     * `revelados` como `contenido` cuando su tipo_dato es object/array_string/
     * array_object (ver docs/prompt.md, punto 8 y 10 de guardar_campo_cliente:
     * "contenido siempre como string, incluso para una estructura compleja") —
     * en vez de exploded fields. Se decodifican aquí, antes de que corran las
     * reglas de 'array', para aceptar ambas formas de envío (string JSON o
     * arreglo/objeto nativo, como en una request JSON pura) sin duplicar
     * validación. `revelados` se decodifica primero porque cada uno de sus
     * items puede a su vez traer su propio `contenido` en la misma forma.
     */
    protected function prepareForValidation(): void
    {
        $revelados = $this->decodificarSiEsJson($this->input('revelados'));

        if (is_array($revelados)) {
            foreach ($revelados as $i => $item) {
                if (is_array($item) && array_key_exists('contenido', $item)) {
                    $tipoDato = FieldDataType::tryFrom((string) ($item['tipo_dato'] ?? ''));

                    if (in_array($tipoDato, [FieldDataType::Object, FieldDataType::ArrayString, FieldDataType::ArrayObject], true)) {
                        $revelados[$i]['contenido'] = $this->decodificarSiEsJson($item['contenido']) ?? $item['contenido'];
                    }
                }
            }

            $this->merge(['revelados' => $revelados]);
        }

        $tipoDatoRaiz = FieldDataType::tryFrom((string) $this->input('tipo_dato'));

        if (in_array($tipoDatoRaiz, [FieldDataType::Object, FieldDataType::ArrayString, FieldDataType::ArrayObject], true)) {
            $contenido = $this->decodificarSiEsJson($this->input('contenido'));

            if ($contenido !== null) {
                $this->merge(['contenido' => $contenido]);
            }
        }
    }

    /**
     * Decodifica $valor si es un string JSON que representa un arreglo/objeto;
     * si ya es un arreglo (request JSON pura, o item ya decodificado dentro de
     * `revelados`) o no es JSON válido, lo devuelve tal cual (null si no era
     * string ni array, para que el llamador decida el fallback).
     */
    private function decodificarSiEsJson(mixed $valor): mixed
    {
        if (is_array($valor)) {
            return $valor;
        }

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        $decodificado = json_decode($valor, true);

        return is_array($decodificado) ? $decodificado : null;
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
            // Las 10 formas del IRS, o una pseudo-forma ('transversal' para
            // identidad del cliente, 'documentos_extra' para el resto de
            // documentos opcionales) que no pertenecen a una forma en particular.
            'forma' => ['required', Rule::in([...array_map(fn (TaxForm $f) => $f->value, TaxForm::cases()), ...CampoCatalogo::pseudoFormas()])],
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
            'revelados.*.forma' => ['required', Rule::in([...array_map(fn (TaxForm $f) => $f->value, TaxForm::cases()), ...CampoCatalogo::pseudoFormas()])],
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

    /**
     * Delega la validación de catálogo (todo lo que depende de datos
     * dinámicos: existe el campo, calza tipo_campo/tipo_dato, acumular/subcampo
     * son coherentes) a EventoValidator, compartida con ToolExecutor (agente
     * de WhatsApp, sin request HTTP) — ver docs/implementar_agente_n8n.md.
     */
    public function withValidator(ValidatorContract|Validator $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $datos = $this->all();
            $datos['acumular'] = $this->boolean('acumular');

            $errores = (new EventoValidator)->validar($datos, $this->file('file'));

            foreach ($errores as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $validator->errors()->add($campo, $mensaje);
                }
            }
        });
    }
}
