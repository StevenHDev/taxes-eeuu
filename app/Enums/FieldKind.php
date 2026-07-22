<?php

namespace App\Enums;

enum FieldKind: string
{
    case Documento = 'documento';
    case Dato = 'dato';
    case Mixto = 'mixto';
}
