<?php

namespace App\Services\Lectura;

use App\Services\Calculo\Nucleo\Dinero;
use InvalidArgumentException;

/**
 * Las sumas de los registros del negocio de un cliente, una por línea del
 * Schedule C (F6 6.3b). Siempre trae todas las claves de `CLAVES`, tengan o no
 * datos: lo que no tiene datos viene con `conDatos = false`.
 */
final readonly class ResultadoSumas
{
    /** Las líneas de dinero del Schedule C que suman renglones: la 1 sin formulario, la 2 y la 8 a la 27b. */
    public const LINEAS = ['1-sin-formulario', '2', '8', '9', '10', '11', '12', '13', '14', '15', '16a', '16b', '17', '18', '19', '20a', '20b', '21', '22', '23', '24a', '24b', '25', '26', '27a', '27b'];

    /** Las cantidades que no son dinero: millas del negocio y pies cuadrados de la casa y de la oficina. */
    public const CANTIDADES = ['millas', 'pies-casa', 'pies-oficina'];

    /** Todas las claves de `SumasRegistros`, en este orden. */
    public const CLAVES = [...self::LINEAS, ...self::CANTIDADES];

    /**
     * @param  array<string, SumaCategoria>  $sumas  por clave de `CLAVES`
     */
    public function __construct(public array $sumas) {}

    /**
     * @throws InvalidArgumentException si la clave no es una de `CLAVES`
     */
    public function para(string $linea): SumaCategoria
    {
        return $this->sumas[$linea] ?? throw new InvalidArgumentException("La línea {$linea} no es una clave de los registros del negocio.");
    }

    public function total(string $linea): Dinero
    {
        return $this->para($linea)->total;
    }

    public function conDatos(): bool
    {
        return array_any($this->sumas, fn (SumaCategoria $s) => $s->conDatos);
    }
}
