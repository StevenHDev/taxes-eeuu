<?php

namespace App\Enums\Lectura;

/**
 * Cómo terminó un intento de leer un archivo (F4.2).
 */
enum EstadoLectura: string
{
    case EnCola = 'en_cola';
    case Leida = 'leida';
    case Fallida = 'fallida';
}
