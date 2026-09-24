<?php

namespace App\Contracts;

use App\Enums\RolMensajeWhatsapp;

/**
 * Lo mínimo que AgenteConversacionalService necesita de un mensaje para
 * reconstruir el historial de una conversación — sin importar el canal
 * (WhatsApp hoy, portal web mañana). Ver Fase 1 del plan de portal seguro:
 * antes de esta interfaz, el agente dependía directamente de WhatsappMensaje
 * (atado a `telefono`), lo que impedía reusarlo desde otro canal.
 */
interface MensajeConversacion
{
    public function rolConversacion(): RolMensajeWhatsapp;

    public function contenidoConversacion(): string;
}
