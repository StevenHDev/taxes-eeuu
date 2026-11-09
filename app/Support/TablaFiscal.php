<?php

namespace App\Support;

use App\Services\Calculo\Nucleo\Dinero;
use InvalidArgumentException;
use OutOfRangeException;

/**
 * Tabla del IRS guardada como dato (parámetro de tipo `tabla` en
 * `parametros_fiscales`): una lista de filas `{hasta, ...}` en dólares.
 *
 * La fila que le toca a un valor `x` es la primera cuyo `hasta` es mayor o
 * igual a `x` (el IRS lo escribe "Over A but not over B"): un valor justo en
 * el borde pertenece a la fila de arriba. Una fila con `hasta` vacío (null) es
 * "sin límite" y tiene que ser la última. Los demás campos de la fila son
 * libres (`decimal`, `monto`, `soltero`...): quien lee decide qué significan.
 *
 * Sin float: `hasta` son enteros en dólares y los decimales viajan como texto
 * ("0.35") para pasarlos tal cual a Dinero::por().
 */
final readonly class TablaFiscal
{
    /** @var list<array<string, mixed>> */
    private array $filas;

    /**
     * @param  array<int|string, mixed>  $filas
     */
    public function __construct(array $filas)
    {
        throw_if($filas === [], new InvalidArgumentException('La tabla fiscal no tiene filas.'));

        $anterior = null;
        $ultima = array_key_last($filas);

        foreach ($filas as $posicion => $fila) {
            throw_unless(is_array($fila) && array_key_exists('hasta', $fila), new InvalidArgumentException(
                "La fila {$posicion} de la tabla fiscal no trae `hasta`.",
            ));

            $hasta = $fila['hasta'];

            if ($hasta === null) {
                throw_unless($posicion === $ultima, new InvalidArgumentException(
                    'Solo la última fila de la tabla fiscal puede ir sin `hasta`.',
                ));

                continue;
            }

            throw_unless(is_int($hasta), new InvalidArgumentException(
                "El `hasta` de la fila {$posicion} de la tabla fiscal debe ser un entero en dólares.",
            ));
            throw_if($anterior !== null && $hasta <= $anterior, new InvalidArgumentException(
                'Los `hasta` de la tabla fiscal deben ir estrictamente de menor a mayor.',
            ));

            $anterior = $hasta;
        }

        $this->filas = array_values($filas);
    }

    /**
     * La fila que le toca a `$x`: un entero se toma como dólares enteros; un
     * Dinero se compara en centavos (USD 15.000,50 ya no cabe en "hasta 15.000").
     *
     * @return array<string, mixed>
     *
     * @throws OutOfRangeException si ninguna fila cubre el valor (la tabla no termina en "sin límite")
     */
    public function buscar(Dinero|int $x): array
    {
        $centavos = $x instanceof Dinero ? $x->centavos() : $x * 100;

        foreach ($this->filas as $fila) {
            if ($fila['hasta'] === null || $centavos <= $fila['hasta'] * 100) {
                return $fila;
            }
        }

        throw new OutOfRangeException('Ninguna fila de la tabla fiscal cubre ese valor.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filas(): array
    {
        return $this->filas;
    }
}
