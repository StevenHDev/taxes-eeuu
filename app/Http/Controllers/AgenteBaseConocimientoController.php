<?php

namespace App\Http\Controllers;

use App\Models\BaseConocimientoDocumento;
use App\Services\BaseConocimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de carga de la base de conocimiento del agente — adelantado desde
 * Fase 7 (ver docs/implementar_agente_n8n.md, "Panel de administración del
 * agente"). Toda la lógica de conversión/búsqueda vive en
 * App\Services\BaseConocimientoService; este controller solo autoriza y
 * traduce a/desde Inertia.
 */
class AgenteBaseConocimientoController extends Controller
{
    public function __construct(
        private readonly BaseConocimientoService $servicio,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', BaseConocimientoDocumento::class);

        $documentos = BaseConocimientoDocumento::query()
            ->with('subidoPor:id,name')
            ->latest()
            ->get()
            ->map(fn (BaseConocimientoDocumento $d) => [
                'id' => $d->id,
                'nombre_original' => $d->nombre_original,
                'tamano' => $d->tamano,
                'estado' => $d->estado->value,
                'error_mensaje' => $d->error_mensaje,
                'subido_por' => $d->subidoPor?->name,
                'created_at' => $d->created_at,
            ]);

        return Inertia::render('agente/base-conocimiento', ['documentos' => $documentos]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BaseConocimientoDocumento::class);

        $validado = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $this->servicio->subir($validado['file'], $request->user());

        return back()->with('success', 'Documento cargado.');
    }

    public function destroy(BaseConocimientoDocumento $documento): RedirectResponse
    {
        $this->authorize('delete', $documento);

        $this->servicio->eliminar($documento);

        return back()->with('success', 'Documento eliminado.');
    }
}
