<?php

namespace App\Enums\Lectura;

/**
 * Quién puso el valor actual de un dato.
 */
enum OrigenDato: string
{
    case Lector = 'lector';
    case Preparador = 'preparador';
}
