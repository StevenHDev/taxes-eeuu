<?php

namespace App\Http\Controllers;

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

    public function index(): Response
    {
        $this->authorize('viewAny', WhatsappMensaje::class);

        $mensajes = WhatsappMensaje::query()
            ->with('cliente:id,name')
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('created_at')
            ->limit(self::LIMITE_FILAS)
            ->get()
            ->map(fn (WhatsappMensaje $m) => [
                'id' => $m->id,
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

        return Inertia::render('agente/mensajes', [
            'mensajes' => $mensajes,
        ]);
    }
}
