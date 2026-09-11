<?php

namespace App\Enums;

enum EstadoBaseConocimiento: string
{
    case Procesado = 'procesado';
    case Error = 'error';
}
