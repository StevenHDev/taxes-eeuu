<?php

namespace App\Enums;

/**
 * Fase vigente de una conversación con el agente, derivada siempre de los
 * datos ya existentes del cliente (nunca de memoria de la conversación) por
 * EstadoConversacionResolver. Cada fase expone al modelo un subconjunto
 * distinto de tools y su propio bloque de prompt en `agente_prompts`.
 *
 * Las fases del formulario anterior (determinacion_formas, recoleccion,
 * cierre y handoff_portal) se retiraron en F2.11; sus filas viejas de
 * `agente_prompts` quedan como historial y se leen con tryFrom().
 */
enum FaseConversacion: string
{
    case VerificacionCuenta = 'verificacion_cuenta';
    // Solo por el chat del portal (ver AgenteConversacionalService::responder(),
    // parámetro $canalPortal): responde dudas; la entrevista se responde en
    // las pantallas del portal.
    case PortalDudas = 'portal_dudas';
    // Falta firmar el consentimiento §7216: explica y manda el enlace.
    case Consentimiento = 'consentimiento';
    // Entrevista conversacional (nivel 1 y seguimientos no sensibles).
    case Entrevista = 'entrevista';
    // Solo falta lo del portal (sensible, documentos, confirmar) o ya terminó.
    case Seguimiento = 'seguimiento';

    public function label(): string
    {
        return match ($this) {
            self::VerificacionCuenta => 'Verificación de cuenta',
            self::PortalDudas => 'Portal — dudas',
            self::Consentimiento => 'Consentimiento §7216',
            self::Entrevista => 'Entrevista por WhatsApp',
            self::Seguimiento => 'Enlaces al portal, estado y dudas',
        };
    }
}
