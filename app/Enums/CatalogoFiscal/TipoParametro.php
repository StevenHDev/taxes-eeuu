<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Cómo se guarda y se muestra el valor de un parámetro del año.
 */
enum TipoParametro: string
{
    case Monto = 'monto';
    case Porcentaje = 'porcentaje';
    case Numero = 'numero';
    case Tabla = 'tabla';
    case Fecha = 'fecha';
}
