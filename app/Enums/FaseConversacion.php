<?php

namespace App\Enums;

/**
 * Fase vigente de una conversación de WhatsApp, derivada siempre de los datos
 * ya existentes del cliente (nunca de memoria de la conversación) por
 * EstadoConversacionResolver. Cada fase expone al modelo un subconjunto
 * distinto de tools y su propio bloque de prompt en `agente_prompts`.
 */
enum FaseConversacion: string
{
    case VerificacionCuenta = 'verificacion_cuenta';
    case AnoFiscal = 'ano_fiscal';
    case DeterminacionFormas = 'determinacion_formas';
    case Recoleccion = 'recoleccion';
    case Cierre = 'cierre';

    public function label(): string
    {
        return match ($this) {
            self::VerificacionCuenta => 'Verificación de cuenta',
            self::AnoFiscal => 'Confirmación de año fiscal',
            self::DeterminacionFormas => 'Determinación de forma(s)',
            self::Recoleccion => 'Recolección de datos',
            self::Cierre => 'Cierre',
        };
    }
}
