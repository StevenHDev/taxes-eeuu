<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcesarMensajeWhatsappJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook de mensajes entrantes de WhatsApp — Twilio espera un 200 casi
 * inmediato o reintenta la entrega; todo el procesamiento real (guardar el
 * mensaje, invocar al agente) ocurre en la cola, nunca en esta request.
 */
class TwilioWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        ProcesarMensajeWhatsappJob::dispatch($request->all());

        return response('', 200);
    }
}
