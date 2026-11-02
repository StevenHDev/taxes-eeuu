<?php

namespace App\Http\Controllers;

use App\Enums\Entrevista\CanalRespuesta;
use App\Http\Requests\CorregirRespuestaRequest;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\Entrevista\EntrevistaRespuestaReveal;
use App\Models\User;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\RespuestaRechazada;
use App\Services\Entrevista\VistaEntrevistaPreparador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lo que el preparador hace con una respuesta de la entrevista desde la
 * ficha del cliente (F2.9): revelarla si es sensible (con contraseña y
 * registro), ver su historial y corregirla. Corregir pasa por
 * RespuestaGuardador con canal `preparador`, así que reabre una entrevista
 * confirmada y el cliente vuelve a confirmar.
 */
class EntrevistaRespuestaController extends Controller
{
    public function reveal(Request $request, User $cliente, EntrevistaRespuesta $respuesta): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->asegurarDelCliente($cliente, $respuesta);

        EntrevistaRespuestaReveal::query()->create([
            'respuesta_id' => $respuesta->id,
            'revealed_by_id' => $request->user()->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['valor' => $respuesta->valor]);
    }

    public function historial(User $cliente, EntrevistaRespuesta $respuesta, VistaEntrevistaPreparador $vista): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->asegurarDelCliente($cliente, $respuesta);

        return response()->json(['cambios' => $vista->historial($respuesta)]);
    }

    public function update(CorregirRespuestaRequest $request, User $cliente, EntrevistaRespuesta $respuesta, RespuestaGuardador $guardador): RedirectResponse
    {
        try {
            $guardador->guardar(
                $respuesta->entrevista,
                new RespuestaData($respuesta->pregunta_codigo, $request->validated('valor'), $respuesta->persona_id),
                CanalRespuesta::Preparador,
                $request->user(),
            );
        } catch (RespuestaRechazada $e) {
            throw ValidationException::withMessages(['valor' => $e->getMessage()]);
        }

        return back();
    }

    private function asegurarDelCliente(User $cliente, EntrevistaRespuesta $respuesta): void
    {
        abort_unless($respuesta->entrevista->user_id === $cliente->id, 404);
    }
}
