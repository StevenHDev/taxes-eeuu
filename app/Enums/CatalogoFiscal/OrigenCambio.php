<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Quién hizo un cambio al catálogo: una persona desde la pantalla o la carga
 * desde los archivos de datos (comando `catalogo-fiscal:cargar`).
 */
enum OrigenCambio: string
{
    case Pantalla = 'pantalla';
    case Carga = 'carga';
}
