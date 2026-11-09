<?php

namespace App\Services\Calculo\Formularios\Impuesto;

use App\Services\Calculo\Nucleo\Dinero;
use InvalidArgumentException;

/**
 * Tax Computation Worksheet—Line 16 de la Publication 1040 (2025), p. 14 (secciones A,
 * B y C: soltero, MFJ/QSS y MFS) y p. 15 (sección D: cabeza de familia): para un ingreso
 * gravable de USD 100.000 o más, "multiplica la línea 15 por la tasa (b) y réstale la
 * cifra (d)".
 *
 * La tasa y la cifra de resta salen de los tramos del año (`tax_brackets.*`), no se
 * teclean: la cifra de resta de un tramo es (tasa × inicio del tramo) − (impuesto en el
 * inicio del tramo), y así coincide con la del PDF (p. ej. soltero, tramo del 22 %:
 * 0,22 × 48.475 − 5.578,50 = 5.086,00). El resultado se deja en centavos: el IRS permite
 * redondear a dólares, pero es opcional (i1040gi, p. 23).
 */
final class HojaImpuesto
{
    /**
     * @param  string  $estadoCivil  valor de `EstadoCivilIrs`
     * @return array{impuesto: Dinero, tasa: string, resta: Dinero}
     */
    public static function calcular(string $estadoCivil, Dinero $ingresoGravable, int $taxYear = 2025): array
    {
        throw_if(
            TablaImpuesto::aplica($ingresoGravable),
            new InvalidArgumentException('Con menos de USD 100.000 se usa la Tax Table, no la hoja de cálculo.'),
        );

        $tramos = Tramos::paraEstadoCivil($taxYear, $estadoCivil);
        $tramo = $tramos->tramoDe($ingresoGravable);

        $enInicio = $tramos->impuestoEscalado(Dinero::deDolares($tramo['desde']));
        $restaEscalada = $tramo['desde'] * 100 * $tramo['numerador'] - $enInicio;
        $producto = $ingresoGravable->centavos() * $tramo['numerador'];

        return [
            'impuesto' => Dinero::deCentavos(Tramos::dividirRedondeando($producto - $restaEscalada, $tramos->denominador)),
            'tasa' => $tramos->tasaTexto($tramo['numerador']),
            'resta' => Dinero::deCentavos(Tramos::dividirRedondeando($restaEscalada, $tramos->denominador)),
        ];
    }
}
