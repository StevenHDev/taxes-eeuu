<?php

namespace App\Enums;

enum AccionAuditoria: string
{
    case Creado = 'creado';
    case Actualizado = 'actualizado';
    case Eliminado = 'eliminado';
    case InicioSesion = 'inicio_sesion';
    case CierreSesion = 'cierre_sesion';

    public function label(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Actualizado => 'Actualizado',
            self::Eliminado => 'Eliminado',
            self::InicioSesion => 'Inicio de sesión',
            self::CierreSesion => 'Cierre de sesión',
        };
    }
}
