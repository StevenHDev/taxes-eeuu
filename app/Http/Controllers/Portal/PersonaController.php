<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ConChatDeDudas;
use App\Models\Entrevista\EntrevistaPersona;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaGuardador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla 4 de la entrevista (F2.5): las personas que vivieron con el
 * cliente o que mantuvo. Cada una tiene su propio paso (C1–C11 y sus
 * seguimientos). Quitar una persona la archiva con sus respuestas (F0.3).
 */
class PersonaController extends Controller
{
    use ConChatDeDudas;

    public function __construct(
        private readonly PasosEntrevista $pasos,
        private readonly RespuestaGuardador $guardador,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $entrevista = $this->guardador->entrevistaDe($request->user(), CanalRespuesta::Portal);
        $situacion = $this->pasos->situacion($entrevista);

        if (! $this->pasos->conPersonas($situacion)) {
            return to_route('portal.entrevista.paso', ['pantalla' => PasosEntrevista::PANTALLA_PERSONAS]);
        }

        $porPersona = collect($situacion->pantallas[PasosEntrevista::PANTALLA_PERSONAS]['preguntas'] ?? [])
            ->whereNotNull('persona_id')
            ->groupBy('persona_id');

        return Inertia::render('portal/entrevista/personas', [
            'taxYear' => $entrevista->tax_year,
            'avance' => $this->pasos->avance($situacion),
            'personas' => $entrevista->personas->map(fn (EntrevistaPersona $p) => [
                'id' => $p->id,
                'orden' => $p->orden,
                'etiqueta' => $p->etiqueta,
                'total' => $porPersona->get($p->id)?->count() ?? 0,
                'respondidas' => $porPersona->get($p->id)?->where('respondida', true)->count() ?? 0,
            ])->values(),
            'anterior' => route('portal.entrevista.paso', ['pantalla' => PasosEntrevista::PANTALLA_PERSONAS]),
            'siguiente' => route('portal.entrevista.paso', ['pantalla' => PasosEntrevista::PANTALLA_PERSONAS + 1]),
            'mensajes' => $this->mensajesDelChat($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $entrevista = $this->guardador->entrevistaDe($request->user(), CanalRespuesta::Portal);
        $persona = $this->guardador->agregarPersona($entrevista);

        return to_route('portal.entrevista.persona', ['persona' => $persona->id]);
    }

    public function destroy(Request $request, EntrevistaPersona $persona): RedirectResponse
    {
        $entrevista = $this->guardador->entrevistaDe($request->user(), CanalRespuesta::Portal);
        abort_unless($persona->entrevista_id === $entrevista->id, 404);

        $this->guardador->quitarPersona($persona);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quitaste a :persona.', ['persona' => __('Persona :orden', ['orden' => $persona->orden])])]);

        return to_route('portal.entrevista.personas');
    }
}
