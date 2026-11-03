<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;

/**
 * Lo que una regla encontró (F4.5). `clave` lo hace único por entrevista
 * (`R-04#item:12#casilla:2`): correr las reglas de nuevo actualiza la misma
 * excepción en vez de duplicarla. `detalle` dice qué casilla o qué códigos,
 * nunca un monto, un SSN ni un nombre: va a una pantalla y a la bitácora.
 */
final readonly class Hallazgo
{
    /**
     * @param  array<string, mixed>  $detalle
     */
    public function __construct(
        public string $clave,
        public ?EntrevistaDocumento $item = null,
        public ?DocumentoDato $dato = null,
        public array $detalle = [],
    ) {}
}
