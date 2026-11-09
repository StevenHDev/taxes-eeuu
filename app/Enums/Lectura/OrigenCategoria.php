<?php

namespace App\Enums\Lectura;

/**
 * Quién puso la categoría de un renglón: el propio papel o la hoja (`documento`),
 * el modelo de IA (`ia`, tramo 2) o el preparador al corregir.
 */
enum OrigenCategoria: string
{
    case Documento = 'documento';
    case Ia = 'ia';
    case Preparador = 'preparador';
}
