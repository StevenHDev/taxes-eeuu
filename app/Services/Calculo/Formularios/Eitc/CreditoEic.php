<?php

namespace App\Services\Calculo\Formularios\Eitc;

use App\Services\Calculo\Nucleo\Dinero;
use App\Support\ParametrosFiscales;
use App\Support\TablaFiscal;
use InvalidArgumentException;

/**
 * La EIC Table del IRS (Pub. 1040 de 2025, pp. 16 a 27) como cálculo.
 *
 * El IRS arma la tabla por franjas de USD 50: toma el punto medio de la franja, aplica la fórmula del
 * estatuto (26 U.S.C. §32: tasa de entrada hasta el máximo, y desde el inicio de la reducción el máximo
 * menos la tasa de reducción por cada dólar de exceso) y redondea al dólar (50 centavos o más sube).
 * Las cifras salen de `credito_eic.por_hijos` (Rev. Proc. 2024-40, sec. 2.06) y se verifican contra las
 * 1.374 filas de tests/Fixtures/irs/2025/eic-table.csv.
 *
 * Celdas con asterisco (8 en 2025, pp. 19, 20, 24 a 27): son la franja donde cae el fin de la reducción.
 * Por debajo del fin el IRS da un crédito fijo (nota al pie de la tabla) y desde el fin "no puede tomar el
 * crédito". El crédito fijo de la nota es el de la fórmula en el punto medio entre el inicio de la franja
 * y el fin de la reducción; así las 8 notas salen de los parámetros y no de cifras tecleadas.
 *
 * Todo en enteros: nada de coma flotante (las tasas viajan como texto).
 */
final class CreditoEic
{
    /** "Look up the amount ... At least 1" (Pub. 1040 de 2025, p. 16): la tabla empieza en USD 1. */
    private const PRIMER_DOLAR_CENTAVOS = 100;

    /** Ancho de franja de la EIC Table: USD 50 (Pub. 1040 de 2025, p. 16, columnas "At least / But less than"). */
    private const FRANJA_CENTAVOS = 5000;

    /**
     * El crédito de la tabla para el monto de la hoja de trabajo.
     *
     * @param  string  $columna  solo_0..solo_3 (soltero, cabeza de familia, viudo calificado y casado separado que califica) o mfj_0..mfj_3; el 3 es "3 o más"
     * @param  int  $taxYear  año de los parámetros
     *
     * @throws \RuntimeException si falta el parámetro del año
     */
    public static function paraFila(string $columna, Dinero $montoDeLaHoja, int $taxYear = 2025): Dinero
    {
        return self::deLaTabla(ParametrosFiscales::tabla($taxYear, 'credito_eic', 'por_hijos'), $columna, $montoDeLaHoja);
    }

    public static function deLaTabla(TablaFiscal $tabla, string $columna, Dinero $montoDeLaHoja): Dinero
    {
        [$hijos, $conjunta] = self::columna($columna);
        $fila = $tabla->buscar($hijos);
        $monto = $montoDeLaHoja->centavos();

        if ($monto < self::PRIMER_DOLAR_CENTAVOS) {
            return Dinero::deCentavos(0);
        }

        $inicioFranja = intdiv($monto, self::FRANJA_CENTAVOS) * self::FRANJA_CENTAVOS;
        $fin = self::finReduccion($tabla, $columna)->centavos();

        // Desde el fin de la reducción "no puede tomar el crédito" (notas de la tabla).
        if ($inicioFranja >= $fin || $monto >= $fin) {
            return Dinero::deCentavos(0);
        }

        $desde = max($inicioFranja, self::PRIMER_DOLAR_CENTAVOS);
        $hasta = min($inicioFranja + self::FRANJA_CENTAVOS, $fin);
        $medio = intdiv($desde + $hasta, 2);

        $maximo = ((int) $fila['maximo']) * 100;
        $inicioReduccion = ((int) $fila[$conjunta ? 'inicio_reduccion_mfj' : 'inicio_reduccion_otros']) * 100;

        // Las franjas que contienen un quiebre de la fórmula dan el máximo: la del monto de ingreso en que el
        // crédito llega al máximo (p. ej. 17.880 con dos hijos) y la del inicio de la reducción (30.470 en
        // conjunta con hijos). Así lo muestra la tabla (8.450 a 8.500 con 649; 12.700 a 12.750 con 4.328;
        // 30.450 a 30.500 con 4.328).
        $ingresoGanado = ((int) $fila['ingreso_ganado']) * 100;

        foreach ([$ingresoGanado, $inicioReduccion] as $quiebre) {
            if ($desde < $quiebre && $quiebre < $hasta) {
                return Dinero::deDolares((int) $fila['maximo']);
            }
        }

        if ($medio < $inicioReduccion) {
            [$numerador, $denominador] = self::fraccion((string) $fila['tasa_entrada']);
            $dolares = intdiv($medio * $numerador + 50 * $denominador, 100 * $denominador);

            return Dinero::deDolares(min($dolares, (int) $fila['maximo']));
        }

        [$numerador, $denominador] = self::fraccion((string) $fila['tasa_reduccion']);
        $escalado = $maximo * $denominador - ($medio - $inicioReduccion) * $numerador;

        return Dinero::deDolares($escalado <= 0 ? 0 : intdiv($escalado + 50 * $denominador, 100 * $denominador));
    }

    /** Fin de la reducción de la columna (el crédito se acaba): "less than $19,104" y compañía. */
    public static function finReduccion(TablaFiscal $tabla, string $columna): Dinero
    {
        [$hijos, $conjunta] = self::columna($columna);

        return Dinero::deDolares((int) $tabla->buscar($hijos)[$conjunta ? 'fin_reduccion_mfj' : 'fin_reduccion_otros']);
    }

    /** Inicio de la reducción de la columna: el límite de la línea 5 de la hoja A y de la línea 10 de la hoja B. */
    public static function inicioReduccion(TablaFiscal $tabla, string $columna): Dinero
    {
        [$hijos, $conjunta] = self::columna($columna);

        return Dinero::deDolares((int) $tabla->buscar($hijos)[$conjunta ? 'inicio_reduccion_mfj' : 'inicio_reduccion_otros']);
    }

    /** @return array{int, bool} número de hijos de la columna y si es la de casados conjunta */
    private static function columna(string $columna): array
    {
        if (preg_match('/^(solo|mfj)_([0-3])$/', $columna, $m) !== 1) {
            throw new InvalidArgumentException('Columna de la EIC Table inválida.');
        }

        return [(int) $m[2], $m[1] === 'mfj'];
    }

    /** @return array{int, int} "0.1598" -> [1598, 10000] */
    private static function fraccion(string $decimal): array
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $decimal, $m) !== 1) {
            throw new InvalidArgumentException('Tasa decimal inválida.');
        }

        $decimales = $m[2] ?? '';

        return [(int) ($m[1].$decimales), 10 ** strlen($decimales)];
    }
}
