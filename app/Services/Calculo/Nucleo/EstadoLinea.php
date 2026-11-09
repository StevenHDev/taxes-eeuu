<?php

namespace App\Services\Calculo\Nucleo;

enum EstadoLinea: string
{
    case Calculada = 'calculada';
    case NoAplica = 'no_aplica';
    case FaltaDato = 'falta_dato';
    case NoSoportada = 'no_soportada';
}
