<?php

namespace App\Http\Controllers;

use App\Enums\FaseConversacion;
use App\Models\AgenteToolEstado;
use App\Services\WhatsappAgent\ToolDefinitions;
use App\Support\AgenteToolEstados;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista y toggle por fase de las tools del agente conversacional — adelantado
 * desde Fase 7 (ver docs/implementar_agente_n8n.md, "Panel de administración
 * del agente"). Solo lectura del catálogo (App\Services\WhatsappAgent\
 * ToolDefinitions sigue siendo código, no se pueden crear tools nuevas desde
 * acá) más un interruptor activo/inactivo por fase (App\Support\
 * AgenteToolEstados).
 */
class AgenteToolController extends Controller
{
    /**
     * Recoleccion y Cierre usan siempre el mismo conjunto de tools por
     * diseño (ver el comentario en ToolDefinitions::paraFase) — togglear una
     * en cualquiera de las dos aplica a ambas, para que no puedan divergir
     * por accidente desde este panel.
     *
     * @var array<int, FaseConversacion>
     */
    private const FASES_ACOPLADAS = [FaseConversacion::Recoleccion, FaseConversacion::Cierre];

    public function index(): Response
    {
        $this->authorize('viewAny', AgenteToolEstado::class);

        $fases = collect(FaseConversacion::cases())->map(fn (FaseConversacion $fase) => [
            'fase' => $fase->value,
            'label' => $fase->label(),
            'tools' => collect(ToolDefinitions::paraFase($fase))
                ->pluck('function')
                // `think` es infraestructura del loop (espacio de razonamiento
                // sin efecto real), no una capacidad de negocio — no tiene
                // sentido exponerla para apagar desde acá.
                ->reject(fn (array $funcion) => $funcion['name'] === 'think')
                ->map(fn (array $funcion) => [
                    'nombre' => $funcion['name'],
                    'descripcion' => $funcion['description'],
                    'activo' => AgenteToolEstados::activo($fase, $funcion['name']),
                ])
                ->values(),
        ]);

        return Inertia::render('agente/tools', ['fases' => $fases]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', AgenteToolEstado::class);

        $validado = $request->validate([
            'fase' => ['required', Rule::enum(FaseConversacion::class)],
            'tool_name' => ['required', 'string'],
            'activo' => ['required', 'boolean'],
        ]);

        $fase = FaseConversacion::from($validado['fase']);

        $nombresValidos = collect(ToolDefinitions::paraFase($fase))->pluck('function.name')->all();
        abort_unless(in_array($validado['tool_name'], $nombresValidos, true), 422, 'Esa tool no existe en esta fase.');

        $fasesAAplicar = in_array($fase, self::FASES_ACOPLADAS, true) ? self::FASES_ACOPLADAS : [$fase];

        foreach ($fasesAAplicar as $f) {
            AgenteToolEstado::query()->updateOrCreate(
                ['fase' => $f->value, 'tool_name' => $validado['tool_name']],
                ['activo' => $validado['activo']],
            );
        }

        AgenteToolEstados::invalidate();

        return back()->with('success', 'Tool actualizada.');
    }
}
