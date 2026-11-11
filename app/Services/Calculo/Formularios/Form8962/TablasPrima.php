<?php

namespace App\Services\Calculo\Formularios\Form8962;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Dinero;
use App\Support\ParametrosFiscales;
use RuntimeException;

/**
 * Las tres tablas de las instrucciones del Form 8962 de 2025 (i8962), leídas de `parametros_fiscales`:
 * - Tablas 1-1, 1-2 y 1-3 (p. 8 y 9): línea federal de pobreza por tamaño de la familia; con más de 8
 *   personas se suma el adicional por persona de la nota de cada tabla.
 * - Tabla 2 (p. 11): cifra aplicable según el porcentaje entero de la línea 5.
 * - Tabla 5 (p. 18): tope de la devolución del adelanto según la línea 5 y el estado civil ("Single" o
 *   "any other filing status"); con 400 o más no hay tope.
 *
 * Todas lanzan `RuntimeException` si el parámetro del año falta o está pendiente.
 */
final class TablasPrima
{
    public const TABLA_48_ESTADOS = '48_estados';

    public const TABLA_ALASKA = 'alaska';

    public const TABLA_HAWAII = 'hawaii';

    /** Clave del parámetro de la línea de pobreza de una tabla. */
    public static function claveLineaPobreza(string $tabla): string
    {
        return 'linea_pobreza_'.$tabla;
    }

    /** Línea 4: la línea de pobreza de la tabla (1-1, 1-2 o 1-3) para el tamaño de la familia (1 o más). */
    public static function lineaPobreza(int $anio, string $tabla, int $tamano): Dinero
    {
        $clave = self::claveLineaPobreza($tabla);
        $filas = ParametrosFiscales::tabla($anio, 'credito_prima_salud', $clave)->filas();
        $ultima = end($filas);
        $tope = (int) $ultima['hasta'];

        if ($tamano <= $tope) {
            foreach ($filas as $fila) {
                if ($fila['hasta'] === $tamano) {
                    return Dinero::deDolares((int) $fila['monto']);
                }
            }

            throw new RuntimeException("La tabla [credito_prima_salud.{$clave}] de {$anio} no trae el tamaño {$tamano}.");
        }

        // Nota de cada tabla: "If your family size was more than 8 people, add $X for each additional person".
        $adicional = Apoyo::parametroEnDolares($anio, 'credito_prima_salud', $clave.'_adicional');

        return Dinero::deDolares((int) $ultima['monto'])->mas($adicional->por((string) ($tamano - $tope)));
    }

    /** Línea 7: la cifra aplicable (texto decimal, "0.0800") para el porcentaje de la línea 5. */
    public static function cifraAplicable(int $anio, int $porcentaje): string
    {
        $fila = ParametrosFiscales::tabla($anio, 'credito_prima_salud', 'tabla_cifra_aplicable')->buscar($porcentaje);

        return (string) $fila['decimal'];
    }

    /** Línea 28: el tope de la devolución, o null si no hay tope (línea 5 de 400 o más: la 28 queda en blanco). */
    public static function topeDevolucion(int $anio, int $porcentaje, EstadoCivilIrs $estadoCivil): ?Dinero
    {
        $fila = ParametrosFiscales::tabla($anio, 'credito_prima_salud', 'tabla_tope_devolucion')->buscar($porcentaje);
        $valor = $fila[$estadoCivil === EstadoCivilIrs::Single ? 'soltero' : 'otros'] ?? null;

        return $valor === null ? null : Dinero::deDolares((int) $valor);
    }
}
