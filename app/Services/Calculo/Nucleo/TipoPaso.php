<?php

namespace App\Services\Calculo\Nucleo;

enum TipoPaso: string
{
    case Entrada = 'entrada';
    case Parametro = 'parametro';
    case Operacion = 'operacion';
    case Resultado = 'resultado';
}
