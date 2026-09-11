<?php

namespace App\Observers;

use App\Events\WhatsappMensajeRecibido;
use App\Models\WhatsappMensaje;

/**
 * Dispara el broadcast en tiempo real por cada WhatsappMensaje que se guarda,
 * sin importar de dónde venga (mensaje entrante del cliente, respuesta del
 * agente, o envío manual de un preparador) — un solo punto, en vez de
 * repetir `broadcast(new ...)` en ProcesarMensajeWhatsappJob y
 * ClienteController::enviarMensajeWhatsapp por separado.
 */
class WhatsappMensajeObserver
{
    public function created(WhatsappMensaje $mensaje): void
    {
        // WhatsappMensajeRecibido es ShouldBroadcastNow (síncrono, ver esa
        // clase) para que la UI se actualice al instante — pero eso significa
        // que cualquier falla de Reverb (red, certificado, el servicio caído)
        // se propaga hasta acá y, sin este try/catch, tumba TODO lo que
        // dispara el guardado: el mensaje entrante del cliente nunca se
        // guarda, el agente nunca corre, el cliente se queda sin respuesta.
        // Que la bandeja no se actualice en vivo una vez es tolerable; que el
        // agente deje de responder no lo es.
        try {
            broadcast(new WhatsappMensajeRecibido($mensaje));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
