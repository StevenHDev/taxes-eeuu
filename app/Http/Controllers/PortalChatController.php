<?php

namespace App\Http\Controllers;

use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\PortalMensaje;
use App\Models\User;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Chat de dudas del portal seguro del cliente (ver App\Contracts\MensajeConversacion
 * y App\Models\PortalMensaje): mismo AgenteConversacionalService que
 * responde por WhatsApp, en la fase PortalDudas, sobre un historial propio.
 * Solo texto: los documentos se suben en la entrevista (F2.6) y el chat no
 * recibe archivos desde F2.11.
 */
class PortalChatController extends Controller
{
    public function __construct(private readonly AgenteConversacionalService $agente) {}

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
            'contenido' => ['required', 'string', 'max:4000'],
        ]);

        PortalMensaje::query()->create([
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => $datos['contenido'],
        ]);

        $resultado = $this->agente->responder($cliente, $this->historial($cliente), $cliente, canalPortal: true);

        PortalMensaje::query()->create([
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Agente,
            'contenido' => $resultado['texto'],
            'prompt_version' => $resultado['prompt_version'],
        ]);

        // No redirect()->route('portal.chat'): este mismo endpoint también lo
        // usa el chat de dudas de la entrevista (F2.5) — back() vuelve a la
        // página desde la que se envió.
        return back();
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
