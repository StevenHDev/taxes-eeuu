<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcesarMensajeWhatsappJob;
use App\Services\Whatsapp\WhatsappChannel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook de mensajes entrantes de WhatsApp — la validación de firma y la
 * normalización del payload las resuelve el `WhatsappChannel` enlazado en
 * AppServiceProvider (Twilio). Responde rápido siempre; todo el
 * procesamiento real ocurre en la cola.
 */
class WhatsappWebhookController extends Controller
{
    public function __construct(private readonly WhatsappChannel $canal) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($this->canal->validarFirma($request), 403, 'Firma inválida.');

        $mensaje = $this->canal->normalizarEntrante($request);

        if ($mensaje !== null) {
            ProcesarMensajeWhatsappJob::dispatch($mensaje);
        }

        return response('', 200);
    }
}
