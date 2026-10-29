<?php

namespace App\Enums;

enum AccionAuditoria: string
{
    case Creado = 'creado';
    case Actualizado = 'actualizado';
    case Eliminado = 'eliminado';
    case Archivado = 'archivado';
    case Restaurado = 'restaurado';
    case InicioSesion = 'inicio_sesion';
    case CierreSesion = 'cierre_sesion';

    public function label(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Actualizado => 'Actualizado',
            self::Eliminado => 'Eliminado',
            self::Archivado => 'Archivado',
            self::Restaurado => 'Restaurado',
            self::InicioSesion => 'Inicio de sesión',
            self::CierreSesion => 'Cierre de sesión',
        };
    }
}
