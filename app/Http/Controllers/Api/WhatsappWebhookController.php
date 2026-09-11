<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcesarMensajeWhatsappJob;
use App\Services\Whatsapp\WhatsappChannel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook de mensajes entrantes de WhatsApp, agnóstico de proveedor — la
 * validación de firma y la normalización del payload las resuelve el
 * `WhatsappChannel` vigente (Twilio o Meta, según
 * `config('services.whatsapp.provider')` — ver AppServiceProvider). Responde
 * rápido siempre; todo el procesamiento real ocurre en la cola.
 */
class WhatsappWebhookController extends Controller
{
    public function __construct(private readonly WhatsappChannel $canal) {}

    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('get')) {
            return $this->canal->manejarHandshake($request) ?? response('', 404);
        }

        abort_unless($this->canal->validarFirma($request), 403, 'Firma inválida.');

        $mensaje = $this->canal->normalizarEntrante($request);

        if ($mensaje !== null) {
            ProcesarMensajeWhatsappJob::dispatch($mensaje);
        }

        return response('', 200);
    }
}
