<?php

namespace App\Enums;

enum FieldMode: string
{
    case Archivo = 'archivo';
    case Texto = 'texto';

    /**
     * El cliente fue consultado por este campo (siempre opcional, nunca
     * obligatorio — ver FieldState::NoAplica) y respondió que no lo tiene o
     * que no aplica en su caso. No lleva contenido ni archivo asociado.
     */
    case NoAplica = 'no_aplica';
}
