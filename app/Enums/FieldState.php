<?php

namespace App\Enums;

enum FieldState: string
{
    case Recibido = 'recibido';
    case Pendiente = 'pendiente';
    case Invalido = 'invalido';

    public function label(): string
    {
        return match ($this) {
            self::Recibido => 'Recibido',
            self::Pendiente => 'Pendiente',
            self::Invalido => 'Inválido',
        };
    }
}
