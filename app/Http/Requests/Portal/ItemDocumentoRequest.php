<?php

namespace App\Http\Requests\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Services\Entrevista\RespuestaGuardador;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las acciones sobre un ítem del checklist (F3.3): autoriza al
 * cliente y carga el ítem solo de su propia entrevista; si es de otro (o ya
 * no está vigente) responde 404 como si no existiera.
 */
abstract class ItemDocumentoRequest extends FormRequest
{
    private ?Entrevista $entrevista = null;

    private ?EntrevistaDocumento $item = null;

    public function authorize(): bool
    {
        return $this->user()?->esCliente() ?? false;
    }

    public function entrevista(): Entrevista
    {
        return $this->entrevista ??= app(RespuestaGuardador::class)->entrevistaDe($this->user(), CanalRespuesta::Portal);
    }

    public function item(): EntrevistaDocumento
    {
        return $this->item ??= $this->entrevista()->documentos()->vigentes()->findOrFail((int) $this->route('item'));
    }
}
