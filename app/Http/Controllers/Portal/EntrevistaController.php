<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ConChatDeDudas;
use App\Http\Requests\Portal\GuardarPasoRequest;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\RespuestaRechazada;
use App\Services\Entrevista\SituacionEntrevista;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La entrevista en el portal (F2.5): el inicio con el avance de las 8
 * pantallas y un paso por vez. Cada paso manda solo sus preguntas, ya
 * filtradas por el motor, y al guardar el servidor decide cuál sigue.
 */
class EntrevistaController extends Controller
{
    use ConChatDeDudas;

    public function __construct(
        private readonly PasosEntrevista $pasos,
        private readonly RespuestaGuardador $guardador,
        private readonly DocumentosEntrevista $documentos,
    ) {}

    public function inicio(Request $request): Response
    {
        $entrevista = $this->entrevista($request);
        $situacion = $this->pasos->situacion($entrevista);
        $avance = $this->pasos->avance($situacion);

        return Inertia::render('portal/entrevista/inicio', [
            'taxYear' => $entrevista->tax_year,
            'pantallas' => $avance,
            'continuarEn' => collect($avance)->firstWhere(fn (array $p) => $p['estado'] !== 'completa')['numero'] ?? null,
            'personas' => $entrevista->personas()->count(),
            'conPersonas' => $this->pasos->conPersonas($situacion),
            'documentos' => [
                'total' => count($situacion->documentos),
                'faltan' => collect($this->documentos->lista($entrevista, $situacion, false))->where('archivos', [])->count(),
            ],
        ]);
    }

    public function paso(Request $request, int $pantalla, string $paso = 'entrada'): Response|RedirectResponse
    {
        abort_unless($pantalla >= 1 && $pantalla <= PasosEntrevista::PANTALLAS, 404);

        $entrevista = $this->entrevista($request);
        $situacion = $this->pasos->situacion($entrevista);
        $preguntas = $this->pasos->preguntas($entrevista, $situacion, $pantalla, $paso);

        // Un seguimiento sin preguntas (porque cambió una respuesta) no se
        // muestra vacío: sigue de largo.
        if ($paso === 'seguimiento' && $preguntas->isEmpty()) {
            return redirect()->to($this->pasos->siguiente($entrevista, $pantalla, $paso));
        }

        return $this->pagina($request, $entrevista, $situacion, $preguntas, $pantalla, $paso);
    }

    public function persona(Request $request, EntrevistaPersona $persona, string $paso = 'entrada'): Response|RedirectResponse
    {
        $entrevista = $this->entrevista($request);
        abort_unless($persona->entrevista_id === $entrevista->id, 404);

        $situacion = $this->pasos->situacion($entrevista);

        if (! $this->pasos->conPersonas($situacion)) {
            return to_route('portal.entrevista.paso', ['pantalla' => PasosEntrevista::PANTALLA_PERSONAS]);
        }

        $preguntas = $this->pasos->preguntas($entrevista, $situacion, PasosEntrevista::PANTALLA_PERSONAS, $paso, $persona->id);

        if ($paso === 'seguimiento' && $preguntas->isEmpty()) {
            return to_route('portal.entrevista.personas');
        }

        return $this->pagina($request, $entrevista, $situacion, $preguntas, PasosEntrevista::PANTALLA_PERSONAS, $paso, $persona);
    }

    public function guardar(GuardarPasoRequest $request): RedirectResponse
    {
        $entrevista = $request->entrevista();
        $persona = $request->persona();

        if ($persona !== null) {
            abort_unless($persona->entrevista_id === $entrevista->id, 404);
        }

        try {
            DB::transaction(function () use ($request, $entrevista, $persona) {
                foreach ($request->respuestasAGuardar() as $codigo => $valor) {
                    $this->guardador->guardar($entrevista, new RespuestaData($codigo, $valor, $persona?->id), CanalRespuesta::Portal, $request->user());
                }
            });
        } catch (RespuestaRechazada $e) {
            throw ValidationException::withMessages(["respuestas.{$e->codigo}" => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guardado.')]);

        return redirect()->to($this->pasos->siguiente($entrevista->fresh(), $request->pantalla(), $request->paso(), $persona?->id));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentosDelPaso(Entrevista $entrevista, SituacionEntrevista $situacion, int $pantalla, string $paso, ?EntrevistaPersona $persona, bool $ingles): array
    {
        $enEstePaso = match (true) {
            $persona !== null => fn (array $d) => $d['persona_id'] === $persona->id,
            $pantalla === 1 && $paso === 'entrada' => fn (array $d) => $d['persona_id'] === null && $d['grupo'] === 'identidad',
            default => null,
        };

        if ($enEstePaso === null) {
            return [];
        }

        return array_values(array_filter($this->documentos->lista($entrevista, $situacion, $ingles), $enEstePaso));
    }

    private function entrevista(Request $request): Entrevista
    {
        return $this->guardador->entrevistaDe($request->user(), CanalRespuesta::Portal);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $preguntas
     */
    private function pagina(Request $request, Entrevista $entrevista, SituacionEntrevista $situacion, Collection $preguntas, int $pantalla, string $paso, ?EntrevistaPersona $persona = null): Response
    {
        $cliente = $request->user();
        $ingles = str_starts_with(app()->getLocale(), 'en');

        return Inertia::render('portal/entrevista/paso', [
            'taxYear' => $entrevista->tax_year,
            'pantalla' => $pantalla,
            'paso' => $paso,
            'persona' => $persona?->only(['id', 'orden', 'etiqueta']),
            'preguntas' => $this->pasos->paraPagina($entrevista, $preguntas, $pantalla, $paso, $persona?->id, $ingles),
            'conConyuge' => $this->pasos->conConyuge($situacion),
            'avance' => $this->pasos->avance($situacion),
            'accion' => $persona !== null
                ? route('portal.entrevista.persona.guardar', ['persona' => $persona->id, 'paso' => $paso])
                : route('portal.entrevista.guardar', ['pantalla' => $pantalla, 'paso' => $paso]),
            'anterior' => $this->pasos->anterior($pantalla, $paso, $persona?->id),
            // El nombre viene de la cuenta: el cliente confirma o corrige. El
            // teléfono y el correo ni se preguntan (ya están en la cuenta).
            'sugeridos' => $pantalla === 1 ? ['A1' => $cliente->name] : (object) [],
            // F2.6: los documentos de este paso se suben aquí mismo (la
            // identidad en la pantalla 1, la tarjeta de cada persona en su paso).
            'documentos' => $this->documentosDelPaso($entrevista, $situacion, $pantalla, $paso, $persona, $ingles),
            'mensajes' => $this->mensajesDelChat($cliente),
        ]);
    }
}
