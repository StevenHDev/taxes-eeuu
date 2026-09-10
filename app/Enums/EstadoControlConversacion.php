<?php

namespace App\Enums;

/**
 * Quién controla una conversación de WhatsApp en este momento — ver sección
 * ESCALAMIENTO A HUMANO de docs/implementar_agente_n8n.md. Mientras el estado
 * sea Humano, ProcesarMensajeWhatsappJob guarda los mensajes entrantes pero no
 * invoca al agente.
 */
enum EstadoControlConversacion: string
{
    case Agente = 'agente';
    case Humano = 'humano';
}
