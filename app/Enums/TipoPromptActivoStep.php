<?php

namespace App\Enums;

enum TipoPromptActivoStep: string
{
    case Simple = 'simple';
    case Condicional = 'condicional';
    case Bifurcacion = 'bifurcacion';
    case DocumentoConNota = 'documento_con_nota';
}
