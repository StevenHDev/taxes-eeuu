<?php

namespace App\Enums;

/**
 * Fase vigente de una conversación de WhatsApp, derivada siempre de los datos
 * ya existentes del cliente (nunca de memoria de la conversación) por
 * EstadoConversacionResolver. Cada fase expone al modelo un subconjunto
 * distinto de tools y su propio bloque de prompt en `agente_prompts`.
 *
 * No hay una fase separada para "confirmar año fiscal": en el prompt actual
 * (prompt_actuales/promptBase.md, PASO 0.5 y PASO A-D) esos dos pasos ocurren
 * seguidos, sin ninguna tool de por medio entre ellos — no hay ningún dato en
 * la base que distinga "año fiscal ya confirmado, forma todavía no" de "nada
 * confirmado todavía". El modelo re-deriva el año fiscal ya confirmado del
 * propio historial de la conversación (ver AgenteConversacionalService), como
 * ya hacía el orquestador de n8n; DeterminacionFormas cubre ambos pasos.
 */
enum FaseConversacion: string
{
    case VerificacionCuenta = 'verificacion_cuenta';
    case DeterminacionFormas = 'determinacion_formas';
    case Recoleccion = 'recoleccion';
    case Cierre = 'cierre';
    // Solo se usa por el canal del portal web (ver AgenteConversacionalService::responder(),
    // parámetro $canalPortal): mismo agente, pero con el formulario del portal — no este
    // chat — a cargo de recolectar. Nunca la deriva EstadoConversacionResolver, que solo
    // conoce Recoleccion/Cierre para ese mismo estado de datos.
    case PortalDudas = 'portal_dudas';
    // Fase 4 del handoff completo (ver [[project_portal_seguro_documentos_sensibles]]):
    // el canal de WhatsApp (nunca el portal — ver $canalPortal) se fuerza acá en vez de
    // Recoleccion una vez que ya hay forma(s) declarada(s) y todavía queda algo
    // obligatorio pendiente — el formulario del portal es quien recolecta ahora, este
    // canal solo entrega el link (una vez) y responde dudas. Cierre NUNCA se reemplaza
    // por esta fase: la atestación final sigue ocurriendo por WhatsApp (ver cierre.md).
    case HandoffPortal = 'handoff_portal';

    public function label(): string
    {
        return match ($this) {
            self::VerificacionCuenta => 'Verificación de cuenta',
            self::DeterminacionFormas => 'Año fiscal y determinación de forma(s)',
            self::Recoleccion => 'Recolección de datos',
            self::Cierre => 'Cierre',
            self::PortalDudas => 'Portal — dudas (formulario a cargo de recolectar)',
            self::HandoffPortal => 'WhatsApp — entrega del link del portal y dudas',
        };
    }
}
