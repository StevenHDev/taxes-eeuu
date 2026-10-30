<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Http\Controllers\Controller;
use App\Services\Entrevista\ConfirmacionEntrevista;
use App\Services\Entrevista\RespuestaGuardador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use LogicException;

/**
 * El cliente confirma su entrevista en el portal (F2.7). Queda una
 * atestación con el texto que vio, en su idioma, y el hash de lo que
 * confirmó.
 */
class ConfirmarEntrevistaController extends Controller
{
    public function __invoke(Request $request, ConfirmacionEntrevista $confirmacion, RespuestaGuardador $guardador): RedirectResponse
    {
        $entrevista = $guardador->entrevistaDe($request->user(), CanalRespuesta::Portal);

        try {
            $confirmacion->confirmar($entrevista, __('Confirmo que la información y los documentos que entregué son correctos y completos.'));
        } catch (LogicException $e) {
            throw ValidationException::withMessages(['confirmar' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recibimos tu información.')]);

        return to_route('portal.entrevista.resumen');
    }
}
