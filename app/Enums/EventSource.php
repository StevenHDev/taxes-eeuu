<?php

namespace App\Enums;

enum EventSource: string
{
    case AgenteIa = 'agente_ia';
    case Preparador = 'preparador';
    case Administrador = 'administrador';
    // El propio cliente, escribiendo directo en un campo del formulario del
    // portal seguro (ver PortalFormularioController) — a diferencia de
    // AgenteIa (el mismo cliente, pero vía tool call del agente conversacional)
    // y de Preparador/Administrador (alguien del equipo editando en su nombre).
    case Cliente = 'cliente';

    public function label(): string
    {
        return match ($this) {
            self::AgenteIa => 'Agente conversacional',
            self::Preparador => 'Preparador',
            self::Administrador => 'Administrador',
            self::Cliente => 'Cliente (formulario del portal)',
        };
    }
}
