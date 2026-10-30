<?php

namespace App\Http\Requests\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaGuardador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Subir uno o varios archivos a un documento de la lista (F2.6). Solo se
 * aceptan documentos que la entrevista pide (para esa persona, en el bloque
 * C), en los formatos del catálogo y de hasta 20 MB cada uno, como hoy.
 */
class SubirDocumentoRequest extends FormRequest
{
    private const MAXIMO_KB = 20480;

    private ?Entrevista $entrevista = null;

    public function authorize(): bool
    {
        return $this->user()?->usaEntrevistaCatalogo() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extensiones = app(DocumentosEntrevista::class)->extensiones($this->entrevista()->tax_year, $this->codigo());

        return [
            'persona_id' => ['nullable', 'integer'],
            'archivos' => ['required', 'array', 'min:1', 'max:10'],
            'archivos.*' => ['file', 'max:'.self::MAXIMO_KB, 'extensions:'.implode(',', $extensiones)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivos.required' => __('Elige al menos un archivo.'),
            'archivos.*.max' => __('Cada archivo puede pesar hasta 20 MB.'),
            'archivos.*.extensions' => __('Formato no permitido. Usa :formatos.', ['formatos' => __('PDF, JPG, PNG o HEIC')]),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $pasos = app(PasosEntrevista::class);
            $persona = $this->persona();

            if ($this->filled('persona_id') && $persona === null) {
                $validator->errors()->add('archivos', __('Esa persona no es de tu entrevista.'));

                return;
            }

            if (! $pasos->situacion($this->entrevista())->tieneDocumento($this->codigo(), $persona?->id)) {
                $validator->errors()->add('archivos', __('Este documento no está en tu lista.'));
            }
        }];
    }

    public function codigo(): string
    {
        return (string) $this->route('codigo');
    }

    public function entrevista(): Entrevista
    {
        return $this->entrevista ??= app(RespuestaGuardador::class)->entrevistaDe($this->user(), CanalRespuesta::Portal);
    }

    public function persona(): ?EntrevistaPersona
    {
        return $this->filled('persona_id')
            ? EntrevistaPersona::query()->where('entrevista_id', $this->entrevista()->id)->find((int) $this->input('persona_id'))
            : null;
    }
}
