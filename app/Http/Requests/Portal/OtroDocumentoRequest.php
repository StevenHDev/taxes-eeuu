<?php

namespace App\Http\Requests\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Models\Entrevista\Entrevista;
use App\Services\Entrevista\RespuestaGuardador;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Un "otro documento" que el cliente agrega fuera de la lista: descripción y
 * sus archivos (F3.3). Formatos: PDF, JPG, PNG o HEIC.
 */
class OtroDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esCliente() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:120'],
            'archivos' => ['required', 'array', 'min:1', 'max:10'],
            'archivos.*' => ['file', 'max:'.SubirDocumentoRequest::MAXIMO_KB, 'extensions:pdf,jpg,jpeg,png,heic'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => __('Cuéntanos qué documento es.'),
            'descripcion.max' => __('La descripción puede tener hasta 120 caracteres.'),
        ] + SubirDocumentoRequest::mensajes();
    }

    public function entrevista(): Entrevista
    {
        return app(RespuestaGuardador::class)->entrevistaDe($this->user(), CanalRespuesta::Portal);
    }
}
