<?php

namespace App\Services\Calculo\Formularios\Eitc;

use RuntimeException;

/** Corta la hoja de trabajo del EIC con su resultado (falta un dato, no soportado, no aplica). Solo se usa dentro de Eitc. */
final class SinCalculo extends RuntimeException
{
    public function __construct(public readonly ResultadoEic $resultado)
    {
        parent::__construct((string) $resultado->motivo);
    }
}
