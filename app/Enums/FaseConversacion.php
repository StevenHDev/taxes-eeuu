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

    public function label(): string
    {
        return match ($this) {
            self::VerificacionCuenta => 'Verificación de cuenta',
            self::DeterminacionFormas => 'Año fiscal y determinación de forma(s)',
            self::Recoleccion => 'Recolección de datos',
            self::Cierre => 'Cierre',
        };
    }
}
