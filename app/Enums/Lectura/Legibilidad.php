<?php

namespace App\Enums\Lectura;

/**
 * Qué tan claro leyó el lector una casilla (F4.3). Con OpenAI la confianza
 * la calcula la plataforma a partir de esto y del formato del valor.
 */
enum Legibilidad: string
{
    case Claro = 'claro';
    case Dudoso = 'dudoso';
    case NoSeVe = 'no_se_ve';
}
