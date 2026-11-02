<?php

namespace App\Http\Requests;

use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El preparador corrige una respuesta de la entrevista de uno de sus
 * clientes (F2.9). Que el valor calce con la pregunta lo valida
 * RespuestaGuardador (ValorRespuesta), igual que en el portal.
 */
class CorregirRespuestaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $cliente */
        $cliente = $this->route('cliente');
        /** @var EntrevistaRespuesta $respuesta */
        $respuesta = $this->route('respuesta');

        return $respuesta->entrevista->user_id === $cliente->id
            && ($this->user()?->can('update', $cliente) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['valor' => ['required']];
    }
}
