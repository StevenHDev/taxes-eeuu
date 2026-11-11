<?php

namespace App\Http\Requests\Expediente;

use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteDatoSinDocumento;
use App\Models\User;
use App\Services\Expediente\DatosSinDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El preparador anota un monto que el cliente no tiene en ningún documento
 * (F6 6.5, último recurso). Rechaza la clave que no está en el catálogo del
 * año, la que ya sale de un documento leído, la que ya tiene un dato anotado
 * y el valor que no es un monto. Una persona de otro cliente da 404.
 */
class AnotarSinDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $cliente */
        $cliente = $this->route('cliente');

        return $this->user()?->can('update', $cliente) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_year' => ['required', 'integer', 'digits:4'],
            'clave' => ['required', 'string', 'max:120'],
            'persona_id' => ['nullable', 'integer'],
            'valor' => ['required', 'string', 'max:30'],
            'motivo' => ['required', 'string', 'max:1000'],
            'fuente' => ['required', Rule::in(ExpedienteDatoSinDocumento::FUENTES)],
            'fuente_fecha' => ['nullable', 'required_if:fuente,cliente_dijo', 'date', 'before_or_equal:today'],
            'fuente_canal' => ['nullable', 'required_if:fuente,cliente_dijo', Rule::in(ExpedienteDatoSinDocumento::CANALES)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $servicio = app(DatosSinDocumento::class);
                $expediente = $this->expediente();
                $clave = (string) $this->input('clave');
                $personaId = $this->filled('persona_id') ? $this->integer('persona_id') : null;

                abort_unless($servicio->personaDelExpediente($expediente, $personaId), 404);

                if (DatosSinDocumento::monto((string) $this->input('valor')) === null) {
                    $validator->errors()->add('valor', __('Escribe un monto en dólares, sin signo y con hasta dos decimales.'));
                }

                if (! $servicio->claveValida($expediente->tax_year, $clave)) {
                    $validator->errors()->add('clave', __('Esa casilla no existe en el catálogo del año o no es un monto.'));

                    return;
                }

                if ($servicio->tieneDocumentoLeido($expediente, $clave, $personaId)) {
                    $validator->errors()->add('clave', __('Este dato ya sale del documento; corrígelo en el visor.'));
                } elseif ($expediente->exists && $servicio->hayVigente($expediente, $clave, $personaId)) {
                    $validator->errors()->add('clave', __('Ya hay un dato anotado sin documento para esa casilla: cámbialo en vez de anotar otro.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => __('Escribe por qué no hay documento.'),
            'fuente_fecha.required_if' => __('Escribe cuándo lo dijo el cliente.'),
            'fuente_canal.required_if' => __('Elige por dónde lo dijo el cliente.'),
        ];
    }

    /**
     * El nombre del campo en los mensajes de error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clave' => __('casilla'),
            'valor' => __('monto'),
            'motivo' => __('motivo'),
            'fuente' => __('fuente'),
            'fuente_fecha' => __('fecha'),
            'fuente_canal' => __('canal'),
        ];
    }

    /**
     * El expediente del cliente en el año; si todavía no existe, uno sin
     * guardar con la entrevista del año (lo crea el servicio al anotar).
     */
    private function expediente(): Expediente
    {
        /** @var User $cliente */
        $cliente = $this->route('cliente');
        $taxYear = $this->integer('tax_year');

        return Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first()
            ?? new Expediente([
                'user_id' => $cliente->id,
                'tax_year' => $taxYear,
                'entrevista_id' => Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->value('id'),
            ]);
    }
}
