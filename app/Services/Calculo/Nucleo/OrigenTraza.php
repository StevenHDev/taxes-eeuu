<?php

namespace App\Services\Calculo\Nucleo;

/**
 * De dónde salió cada entrada de un cálculo. La referencia que lo acompaña
 * (id, código, clave) nunca es el valor sensible.
 */
enum OrigenTraza: string
{
    case Leido = 'leido';
    case Corregido = 'corregido';
    case SinDocumento = 'sin_documento';
    case Respuesta = 'respuesta';
    case Decision = 'decision';
    case Linea = 'linea';
    case Parametro = 'parametro';
}
