<?php

namespace App\Services\Calculo\Nucleo;

enum RolPersona: string
{
    case Contribuyente = 'contribuyente';
    case Conyuge = 'conyuge';
    case Dependiente = 'dependiente';
}
