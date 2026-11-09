<?php

namespace App\Services\Calculo\Formularios\Impuesto;

use App\Services\Calculo\Nucleo\Dinero;
use InvalidArgumentException;

/**
 * Tax Table de la Publication 1040 (2025), p. 2 a 13: el impuesto para un ingreso
 * gravable (línea 15 del 1040) menor de USD 100.000.
 *
 * Método del IRS: la tabla da el impuesto del PUNTO MEDIO de cada franja, calculado con
 * los tramos del año y redondeado a dólares enteros. Las franjas (deducidas del PDF y
 * verificadas contra las 2.062 filas de tests/Fixtures/irs/2025/tax-table.csv):
 *   - 0 a 5: franja de USD 5 (p. 2: 0-5);
 *   - 5 a 25: franjas de USD 10 (p. 2: 5-15, 15-25);
 *   - 25 a 3.000: franjas de USD 25 (p. 2 y 3: 25-50, 50-75 ... 2.975-3.000);
 *   - 3.000 a 100.000: franjas de USD 50 (p. 3: 3.000-3.050 ... p. 13: 99.950-100.000).
 * Cada franja incluye su "At least" y excluye su "But less than".
 *
 * Estas franjas y el corte de USD 100.000 son la forma de la tabla del IRS de 2025, no
 * parámetros del año (por eso no están en `parametros_fiscales`).
 */
final class TablaImpuesto
{
    /** Pub. 1040 (2025), p. 2 y 13: la Tax Table termina en 100.000; desde ahí se usa la hoja. */
    public const LIMITE_DOLARES = 100000;

    private const ANCHO_PRIMERA = 5;     // 0 a 5

    private const ANCHO_BAJA = 10;       // 5 a 25

    private const ANCHO_MEDIA = 25;      // 25 a 3.000

    private const ANCHO_ALTA = 50;       // 3.000 a 100.000

    private const FIN_BAJA = 25;

    private const FIN_MEDIA = 3000;

    /**
     * El impuesto de la fila de la Tax Table que le toca al ingreso gravable.
     *
     * @param  string  $estadoCivil  valor de `EstadoCivilIrs` (single, mfj, mfs, hoh, qss; QSS usa la columna de MFJ)
     */
    public static function paraFila(string $estadoCivil, Dinero $ingresoGravable, int $taxYear = 2025): Dinero
    {
        throw_if($ingresoGravable->esNegativo(), new InvalidArgumentException('La Tax Table no tiene filas con ingreso negativo.'));
        throw_unless(self::aplica($ingresoGravable), new InvalidArgumentException('Desde USD 100.000 se usa la Tax Computation Worksheet, no la Tax Table.'));

        $franja = self::franja($ingresoGravable);
        $medio = Dinero::deCentavos(intdiv($franja['desde']->centavos() + $franja['hasta']->centavos(), 2));

        $tramos = Tramos::paraEstadoCivil($taxYear, $estadoCivil);
        $enDolares = Tramos::dividirRedondeando($tramos->impuestoEscalado($medio), $tramos->denominador * 100);

        return Dinero::deDolares($enDolares);
    }

    /** ¿Le toca la Tax Table? Solo con ingreso gravable menor de USD 100.000 (en centavos: 99.999,99 sí). */
    public static function aplica(Dinero $ingresoGravable): bool
    {
        return $ingresoGravable->centavos() < self::LIMITE_DOLARES * 100;
    }

    /**
     * La franja ("At least" incluido, "But less than" excluido) de un ingreso gravable.
     *
     * @return array{desde: Dinero, hasta: Dinero}
     */
    public static function franja(Dinero $ingresoGravable): array
    {
        $c = $ingresoGravable->centavos();
        throw_if($c < 0 || $c >= self::LIMITE_DOLARES * 100, new InvalidArgumentException('Ingreso fuera de la Tax Table.'));

        if ($c < self::ANCHO_PRIMERA * 100) {
            $desde = 0;
            $ancho = self::ANCHO_PRIMERA * 100;
        } elseif ($c < self::FIN_BAJA * 100) {
            $base = self::ANCHO_PRIMERA * 100;
            $ancho = self::ANCHO_BAJA * 100;
            $desde = $base + intdiv($c - $base, $ancho) * $ancho;
        } elseif ($c < self::FIN_MEDIA * 100) {
            $ancho = self::ANCHO_MEDIA * 100;
            $desde = intdiv($c, $ancho) * $ancho;
        } else {
            $ancho = self::ANCHO_ALTA * 100;
            $desde = intdiv($c, $ancho) * $ancho;
        }

        return ['desde' => Dinero::deCentavos($desde), 'hasta' => Dinero::deCentavos($desde + $ancho)];
    }
}
