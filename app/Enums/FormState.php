<?php

namespace App\Enums;

enum FormState: string
{
    case EnProgreso = 'en_progreso';
    case Completo = 'completo';

    public function label(): string
    {
        return match ($this) {
            self::EnProgreso => 'En progreso',
            self::Completo => 'Completo',
        };
    }
}
