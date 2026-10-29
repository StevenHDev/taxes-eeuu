<?php

namespace App\Enums\CatalogoFiscal;

/**
 * De dónde sale el valor de una línea de un formulario (7.3).
 */
enum OrigenLinea: string
{
    case Casilla = 'casilla';
    case Linea = 'linea';
    case Respuesta = 'respuesta';
    case Calculo = 'calculo';
}
