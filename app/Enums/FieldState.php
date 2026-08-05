<?php

namespace App\Enums;

enum FieldState: string
{
    case Recibido = 'recibido';
    case Pendiente = 'pendiente';
    case Invalido = 'invalido';

    /**
     * El cliente fue consultado por un campo opcional y respondió que no lo
     * tiene o que no aplica en su caso — a diferencia de `Pendiente`, esto
     * es una respuesta explícita del cliente, no la ausencia de una. Solo
     * alcanzable para campos con `obligatorio: false` (ver EventoRequest /
     * CampoClienteUpdateRequest) — nunca cuenta contra la completitud de una
     * forma, igual que un opcional simplemente nunca cargado.
     */
    case NoAplica = 'no_aplica';

    public function label(): string
    {
        return match ($this) {
            self::Recibido => 'Recibido',
            self::Pendiente => 'Pendiente',
            self::Invalido => 'Inválido',
            self::NoAplica => 'No aplica',
        };
    }
}
