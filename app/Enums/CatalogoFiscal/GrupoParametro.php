<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Grupos de la vista única de parámetros del año (7.7 del documento madre).
 */
enum GrupoParametro: string
{
    case DeduccionEstandar = 'deduccion_estandar';
    case DeduccionesDetalladas = 'deducciones_detalladas';
    case AjustesIngreso = 'ajustes_ingreso';
    case DeduccionesSchedule1A = 'deducciones_schedule_1a';
    case Creditos = 'creditos';
    case TopesUmbrales = 'topes_umbrales';
    case Tablas = 'tablas';
}
