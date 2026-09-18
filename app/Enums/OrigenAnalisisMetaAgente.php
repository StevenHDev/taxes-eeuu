<?php

namespace App\Enums;

/**
 * Cómo se disparó un análisis del meta-agente (ver MetaAgenteReporte) —
 * distingue una revisión puntual pedida desde el panel de una corrida
 * automática programada (ver AnalizarConversacionesAgenteCommand).
 */
enum OrigenAnalisisMetaAgente: string
{
    case Manual = 'manual';
    case Programado = 'programado';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Programado => 'Programado',
        };
    }
}
