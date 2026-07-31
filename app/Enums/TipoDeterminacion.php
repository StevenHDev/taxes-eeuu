<?php

namespace App\Enums;

/**
 * Discriminador de `determinaciones_fiscales.tipo` — cada uno de los cuatro
 * resultados que produce el motor de reglas para un cliente en un año fiscal.
 */
enum TipoDeterminacion: string
{
    case FilingStatus = 'filing_status';
    case Dependientes = 'dependientes';
    case Agi = 'agi';
    case Creditos = 'creditos';
}
