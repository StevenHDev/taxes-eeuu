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

    // Fase 6 — motor de cálculo completo (más allá de AGI/créditos): deducción
    // aplicable, QBI, impuesto sobre el ingreso, "otros impuestos" de
    // Schedule 2 Parte II, y la liquidación final (reembolso/saldo a pagar).
    case DeduccionAplicable = 'deduccion_aplicable';
    case Qbi = 'qbi';
    case ImpuestoIngreso = 'impuesto_ingreso';
    case ImpuestoAutoempleo = 'impuesto_autoempleo';
    case ImpuestoMedicareAdicional = 'impuesto_medicare_adicional';
    case Niit = 'niit';
    case Liquidacion = 'liquidacion';
}
