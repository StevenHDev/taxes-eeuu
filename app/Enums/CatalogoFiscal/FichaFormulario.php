<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Si el formulario ya tiene su ficha con líneas (4.0-B) o todavía no. Los
 * Schedules 1, 2 y 3 son "conectores": juntan totales de otros formularios
 * y los llevan al 1040.
 */
enum FichaFormulario: string
{
    case Completa = 'completa';
    case Conector = 'conector';
    case Pendiente = 'pendiente';
}
