<?php

namespace App\Services\Lectura;

use App\Services\Calculo\Nucleo\Dinero;

/**
 * El total de una línea del Schedule C según los registros del cliente (F6
 * 6.3b), con los renglones que lo forman, su confianza y su motivo.
 *
 * `total` es dinero en las líneas de montos. En `millas`, `pies-casa` y
 * `pies-oficina` es una CANTIDAD (millas o pies cuadrados), no dinero: se
 * guarda como un `Dinero` con la cantidad en "dólares" (9.800 millas son
 * 9.800,00) porque el contrato del motor tiene un solo tipo; quien la usa la
 * toma como cantidad (`centavos() / 100`).
 *
 * `conDatos` distingue "no hay nada en los registros" de "los registros dicen
 * cero": sin datos, el total es cero y la calculadora debe decir que falta.
 * `renglonIds` son los ids de `documento_renglones` que forman el total (en
 * las cantidades, que salen de una casilla, queda vacío).
 */
final readonly class SumaCategoria
{
    /**
     * @param  list<int>  $renglonIds
     */
    public function __construct(
        public string $linea,
        public Dinero $total,
        public array $renglonIds,
        public float $confianza,
        public string $motivo,
        public bool $conDatos,
    ) {}
}
