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
        broadcast(new WhatsappMensajeRecibido($mensaje));
    }
}
