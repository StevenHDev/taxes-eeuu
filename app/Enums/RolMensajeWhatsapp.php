<?php

namespace App\Enums;

enum RolMensajeWhatsapp: string
{
    case Cliente = 'cliente';
    case Agente = 'agente';
    case Preparador = 'preparador';
    case Sistema = 'sistema';

    public function label(): string
    {
        return match ($this) {
            self::Cliente => 'Cliente',
            self::Agente => 'Agente conversacional',
            self::Preparador => 'Preparador (control manual)',
            self::Sistema => 'Mensaje automático del sistema',
        };
    }
}
