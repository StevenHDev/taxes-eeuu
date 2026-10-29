<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Si el formulario ya tiene su ficha con líneas (4.0-B) o todavía no.
 */
enum FichaFormulario: string
{
    case Completa = 'completa';
    case Pendiente = 'pendiente';
}
