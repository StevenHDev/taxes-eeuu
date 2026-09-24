<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\AdjuntoWhatsapp;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\PortalMensaje;
use App\Models\User;
use App\Services\DocumentoExtraccion\DocumentoExtraccionService;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use App\Support\ContenidoMensajeConAdjuntos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Chat del portal seguro del cliente (Fase 2 — ver App\Contracts\MensajeConversacion
 * y App\Models\PortalMensaje): mismo AgenteConversacionalService que ya
 * responde por WhatsApp, mismos prompts y tools, sobre un historial propio en
 * vez de whatsapp_mensajes. Un archivo adjunto pasa por el mismo
 * DocumentoExtraccionService que usa WhatsApp (ver AdjuntosWhatsappService) —
 * es el propio agente quien decide, vía guardar_campo_cliente, si y dónde
 * guardarlo (a través de ToolExecutor -> EventoRecoleccionService), exactamente
 * igual que en la conversación por WhatsApp.
 */
class PortalChatController extends Controller
{
    public function __construct(
        private readonly AgenteConversacionalService $agente,
        private readonly DocumentoExtraccionService $extraccion,
    ) {}

    public function index(Request $request): Response
    {
        $cliente = $this->clienteAutenticado($request);

        return Inertia::render('portal/chat', [
            'mensajes' => $this->historial($cliente)->map(fn (PortalMensaje $m) => [
                'id' => $m->id,
                'rol' => $m->rol->value,
                'contenido' => $m->contenido,
                'created_at' => $m->created_at,
            ])->all(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $cliente = $this->clienteAutenticado($request);

        $datos = $request->validate([
            'contenido' => ['nullable', 'string', 'max:4000'],
            'archivo' => ['nullable', 'file', 'max:20480'],
        ]);

        $texto = (string) ($datos['contenido'] ?? '');

        if ($texto === '' && ! $request->hasFile('archivo')) {
            return back()->withErrors(['contenido' => 'Escribe un mensaje o adjunta un archivo.']);
        }

        $adjuntos = $this->resolverAdjunto($request);

        PortalMensaje::query()->create([
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => ContenidoMensajeConAdjuntos::construir($texto, $adjuntos),
        ]);

        $resultado = $this->agente->responder($cliente, $this->historial($cliente), $cliente, $adjuntos, canalPortal: true);

        PortalMensaje::query()->create([
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Agente,
            'contenido' => $resultado['texto'],
            'prompt_version' => $resultado['prompt_version'],
        ]);

        // No redirect()->route('portal.chat'): este mismo endpoint también lo
        // usa el chat embebido en /portal/formulario (Fase 3) — volver
        // siempre a portal.chat sacaría al cliente de esa pantalla después de
        // cada mensaje. back() vuelve a la página desde la que se envió.
        return back();
    }

    /**
     * @return array<int, AdjuntoWhatsapp>
     */
    private function resolverAdjunto(Request $request): array
    {
        if (! $request->hasFile('archivo')) {
            return [];
        }

        $archivo = $request->file('archivo');
        $rutaLocal = (string) $archivo->getRealPath();
        $mimeType = (string) ($archivo->getMimeType() ?? 'application/octet-stream');
        $extraido = $this->extraccion->extraer($rutaLocal, $mimeType);

        return [new AdjuntoWhatsapp(
            referencia: 'portal:'.Str::uuid(),
            rutaLocal: $rutaLocal,
            mimeType: $mimeType,
            texto: $extraido['texto'],
            metodo: $extraido['metodo'],
        )];
    }

    /**
     * @return Collection<int, PortalMensaje>
     */
    private function historial(User $cliente): Collection
    {
        return PortalMensaje::query()->where('cliente_id', $cliente->id)->orderBy('id')->get();
    }

    /**
     * Único punto de esta clase que decide quién puede entrar — sin
     * middleware/policy dedicados: mismo criterio que ya usa
     * DashboardController::miInformacion() para distinguir un cliente del
     * panel de preparadores/administradores.
     */
    private function clienteAutenticado(Request $request): User
    {
        $user = $request->user();

        abort_unless($user->role === UserRole::Client, 403);

        return $user;
    }
}
