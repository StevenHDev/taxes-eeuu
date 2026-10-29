<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Un año en `borrador` se puede cargar y corregir libremente; uno `vigente`
 * ya se usa con clientes y la pantalla avisa antes de cambiarlo.
 */
enum EstadoAnio: string
{
    case Borrador = 'borrador';
    case Vigente = 'vigente';
}
