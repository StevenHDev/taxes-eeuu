<?php

namespace App\Http\Controllers;

use App\Models\PortalMensaje;
use App\Models\WhatsappMensaje;
use Inertia\Inertia;
use Inertia\Response;

class AgenteMensajesController extends Controller
{
    /**
     * Mismo límite/ventana que BitacoraController: DataTable 100%
     * client-side, sin paginación de servidor todavía — si el volumen de
     * mensajes lo amerita más adelante, la solución de fondo es paginación
     * real, fuera de este alcance.
     */
    private const LIMITE_FILAS = 500;

    /**
     * Las conversaciones del agente por los dos canales (F2.9): WhatsApp y el
     * chat de dudas del portal, mezcladas por fecha y con `canal` para
     * filtrarlas.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', WhatsappMensaje::class);

        $desde = now()->subDays(30);

        $whatsapp = WhatsappMensaje::query()
            ->with('cliente:id,name')
            ->where('created_at', '>=', $desde)
            ->latest('created_at')
            ->limit(self::LIMITE_FILAS)
            ->get()
            ->map(fn (WhatsappMensaje $m) => [
                'id' => "whatsapp-{$m->id}",
                'canal' => 'whatsapp',
                'created_at' => $m->created_at,
                'telefono' => $m->telefono,
                'cliente_id' => $m->cliente_id,
                'cliente_nombre' => $m->cliente?->name,
                'rol' => $m->rol->value,
                'contenido' => $m->contenido,
                'proveedor' => $m->proveedor,
                'mensaje_externo_id' => $m->mensaje_externo_id,
                'prompt_version' => $m->prompt_version,
            ]);

        $portal = PortalMensaje::query()
            ->with('cliente:id,name,phone')
            ->where('created_at', '>=', $desde)
            ->latest('created_at')
            ->limit(self::LIMITE_FILAS)
            ->get()
            ->map(fn (PortalMensaje $m) => [
                'id' => "portal-{$m->id}",
                'canal' => 'portal',
                'created_at' => $m->created_at,
                'telefono' => $m->cliente?->phone,
                'cliente_id' => $m->cliente_id,
                'cliente_nombre' => $m->cliente?->name,
                'rol' => $m->rol->value,
                'contenido' => $m->contenido,
                'proveedor' => null,
                'mensaje_externo_id' => null,
                'prompt_version' => $m->prompt_version,
            ]);

        return Inertia::render('agente/mensajes', [
            'mensajes' => $whatsapp->concat($portal)
                ->sortByDesc('created_at')
                ->take(self::LIMITE_FILAS)
                ->values(),
        ]);
    }
}
