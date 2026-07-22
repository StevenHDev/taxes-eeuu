<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Preparer = 'preparer';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Cliente',
            self::Preparer => 'Preparador',
            self::Administrator => 'Administrador',
        };
    }
}
