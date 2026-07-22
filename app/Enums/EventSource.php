<?php

namespace App\Enums;

enum EventSource: string
{
    case AgenteIa = 'agente_ia';
    case Preparador = 'preparador';
    case Administrador = 'administrador';

    public function label(): string
    {
        return match ($this) {
            self::AgenteIa => 'Agente conversacional',
            self::Preparador => 'Preparador',
            self::Administrador => 'Administrador',
        };
    }
}
