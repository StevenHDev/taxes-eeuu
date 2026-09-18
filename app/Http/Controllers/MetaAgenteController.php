<?php

namespace App\Http\Controllers;

use App\Jobs\AnalizarConversacionAgenteJob;
use App\Models\MetaAgenteReporte;
use App\Models\User;
use App\Models\WhatsappMensaje;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel del meta-agente: elegir una o varias conversaciones para analizar
 * puntualmente, y ver los reportes ya generados (manuales o de la corrida
 * automática diaria — ver AnalizarConversacionesAgenteCommand). Misma
 * autorización que la bandeja de mensajes de WhatsApp (WhatsappMensajePolicy):
 * exclusivo de administradores, por la misma razón — sirve para auditar el
 * comportamiento del agente, no es trabajo de caso de un preparador.
 */
class MetaAgenteController extends Controller
{
    private const LIMITE_CONVERSACIONES = 100;

    private const LIMITE_REPORTES = 50;

    public function index(): Response
    {
        $this->authorize('viewAny', WhatsappMensaje::class);

        return Inertia::render('meta-agente/index', [
            'conversaciones' => $this->conversacionesRecientes(),
            'reportes' => $this->reportesRecientes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', WhatsappMensaje::class);

        $validated = $request->validate([
            'telefonos' => ['required', 'array', 'min:1'],
            'telefonos.*' => ['string'],
        ]);

        foreach ($validated['telefonos'] as $telefono) {
            AnalizarConversacionAgenteJob::dispatch($telefono, $request->user()->id);
        }

        return back();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function conversacionesRecientes(): array
    {
        // DB::table(), no el modelo Eloquent: esta consulta es un agregado
        // (MAX/COUNT agrupado por teléfono), no filas reales de
        // whatsapp_mensajes — mezclarla con WhatsappMensaje::query() confunde
        // tanto a Eloquent como a PHPStan sobre qué propiedades existen.
        $filas = DB::table('whatsapp_mensajes')
            ->selectRaw('telefono, MAX(cliente_id) as cliente_id, MAX(created_at) as ultimo_mensaje, COUNT(*) as total_mensajes')
            ->groupBy('telefono')
            ->orderByDesc('ultimo_mensaje')
            ->limit(self::LIMITE_CONVERSACIONES)
            ->get();

        $nombresPorCliente = User::query()
            ->whereIn('id', $filas->pluck('cliente_id')->filter())
            ->pluck('name', 'id');

        return $filas
            ->map(fn ($fila) => [
                'telefono' => $fila->telefono,
                'cliente_id' => $fila->cliente_id,
                'cliente_nombre' => $fila->cliente_id ? $nombresPorCliente->get($fila->cliente_id) : null,
                'ultimo_mensaje' => $fila->ultimo_mensaje,
                'total_mensajes' => (int) $fila->total_mensajes,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportesRecientes(): array
    {
        return MetaAgenteReporte::query()
            ->with(['cliente:id,name', 'disparadoPorUsuario:id,name'])
            ->latest('created_at')
            ->limit(self::LIMITE_REPORTES)
            ->get()
            ->map(fn (MetaAgenteReporte $r) => [
                'id' => $r->id,
                'telefono' => $r->telefono,
                'cliente_nombre' => $r->cliente?->name,
                'rango_desde' => $r->rango_desde,
                'rango_hasta' => $r->rango_hasta,
                'mensajes_analizados' => $r->mensajes_analizados,
                'modelo' => $r->modelo,
                'origen' => $r->origen->value,
                'disparado_por_nombre' => $r->disparadoPorUsuario?->name,
                'hallazgos' => $r->hallazgos,
                'created_at' => $r->created_at,
            ])
            ->all();
    }
}
