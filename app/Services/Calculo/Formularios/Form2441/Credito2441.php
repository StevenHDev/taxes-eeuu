<?php

namespace App\Services\Calculo\Formularios\Form2441;

use App\Services\Calculo\Nucleo\Dinero;
use App\Support\ParametrosFiscales;
use App\Support\TablaFiscal;
use RuntimeException;

/**
 * Tabla de la línea 8 del Form 2441 (2025, p. 1): el decimal según el ingreso ajustado de la línea 7.
 *
 * El IRS la escribe "Over — But not over": la fila de USD 15.000 a 17.000 empieza por encima de 15.000 y
 * termina en 17.000 incluido (así busca `TablaFiscal`). Desde 2026 la tabla trae una segunda columna para la
 * declaración conjunta (`hasta_conjunta`, instrucciones del 2441 de 2026, borrador, p. 6); si la fila la trae y la
 * declaración es conjunta, se usa esa.
 */
final class Credito2441
{
    /**
     * El decimal de la línea 8 ('0.35' … '0.20') para el ingreso ajustado de la línea 7.
     *
     * @throws RuntimeException si falta el parámetro `credito_cuidado_dependientes.tabla` del año
     */
    public static function decimalParaLinea7(Dinero $linea7, int $anio = 2025, bool $conjunta = false): string
    {
        $tabla = ParametrosFiscales::tabla($anio, 'credito_cuidado_dependientes', 'tabla');

        if ($conjunta && self::traeColumnaConjunta($tabla)) {
            $tabla = new TablaFiscal(array_map(
                fn (array $fila): array => [...$fila, 'hasta' => $fila['hasta_conjunta'] ?? null],
                $tabla->filas(),
            ));
        }

        return (string) $tabla->buscar($linea7)['decimal'];
    }

    private static function traeColumnaConjunta(TablaFiscal $tabla): bool
    {
        foreach ($tabla->filas() as $fila) {
            if (array_key_exists('hasta_conjunta', $fila)) {
                return true;
            }
        }

        return false;
    }
}
