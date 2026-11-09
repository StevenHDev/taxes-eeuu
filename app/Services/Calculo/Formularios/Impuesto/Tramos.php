<?php

namespace App\Services\Calculo\Formularios\Impuesto;

use App\Services\Calculo\Nucleo\Dinero;
use App\Support\ParametrosFiscales;
use InvalidArgumentException;

/**
 * Tramos del impuesto del año para un estado civil (parámetros `tax_brackets.*`) y la
 * aritmética exacta que usan la Tax Table y la Tax Computation Worksheet.
 *
 * Sin coma flotante: cada tasa se lleva como entero sobre una potencia de 10 y el
 * impuesto se calcula en "centavos por esa potencia", así el redondeo (a dólar en la
 * Tax Table, a centavo en la hoja) se hace una sola vez al final y no por tramo.
 */
final readonly class Tramos
{
    /**
     * @param  list<array{desde: int, numerador: int}>  $tramos  `desde` en dólares; `numerador` = tasa × $denominador
     */
    private function __construct(public array $tramos, public int $denominador) {}

    /** `$estadoCivil` es el valor del enum `EstadoCivilIrs`: single, mfj, mfs, hoh o qss (QSS usa MFJ). */
    public static function paraEstadoCivil(int $taxYear, string $estadoCivil): self
    {
        $clave = self::claveParametro($estadoCivil);
        $filas = ParametrosFiscales::valorRequerido($taxYear, 'tax_brackets', $clave);

        throw_unless(is_array($filas) && $filas !== [], new InvalidArgumentException(
            "El parámetro fiscal [tax_brackets.{$clave}] de tax_year={$taxYear} no es una lista de tramos.",
        ));

        $decimales = 0;
        $leidos = [];

        foreach ($filas as $fila) {
            $tasa = trim((string) $fila['tasa']);

            throw_unless(preg_match('/^(\d+)(?:\.(\d+))?$/', $tasa, $m) === 1, new InvalidArgumentException(
                "La tasa [{$tasa}] de tax_brackets.{$clave} no es un decimal.",
            ));

            $fraccion = rtrim($m[2] ?? '', '0');
            $decimales = max($decimales, strlen($fraccion));
            $leidos[] = ['desde' => (int) $fila['desde'], 'entero' => $m[1], 'fraccion' => $fraccion];
        }

        $tramos = array_map(
            fn (array $t): array => [
                'desde' => $t['desde'],
                'numerador' => (int) ($t['entero'].str_pad($t['fraccion'], $decimales, '0')),
            ],
            $leidos,
        );

        return new self($tramos, 10 ** $decimales);
    }

    /** Clave del parámetro `tax_brackets.<clave>` para un estado civil. */
    public static function claveParametro(string $estadoCivil): string
    {
        return match ($estadoCivil) {
            'single', 'soltero' => 'single',
            'mfj', 'qss' => 'mfj',
            'mfs' => 'mfs',
            'hoh' => 'hoh',
            default => throw new InvalidArgumentException("Estado civil desconocido [{$estadoCivil}]."),
        };
    }

    /**
     * Impuesto exacto por tramos, en centavos multiplicados por el denominador de las tasas.
     * Un ingreso en el borde de un tramo paga a la tasa del tramo de abajo ("not over").
     */
    public function impuestoEscalado(Dinero $ingreso): int
    {
        $x = max(0, $ingreso->centavos());
        $total = 0;

        foreach ($this->tramos as $i => $tramo) {
            $desde = $tramo['desde'] * 100;

            if ($x <= $desde) {
                break;
            }

            $hasta = isset($this->tramos[$i + 1]) ? $this->tramos[$i + 1]['desde'] * 100 : $x;
            $total += (min($x, $hasta) - $desde) * $tramo['numerador'];
        }

        return $total;
    }

    /** Impuesto por tramos redondeado al centavo (mitad hacia arriba). */
    public function impuesto(Dinero $ingreso): Dinero
    {
        return Dinero::deCentavos(self::dividirRedondeando($this->impuestoEscalado($ingreso), $this->denominador));
    }

    /**
     * El tramo que le toca a un ingreso: el último cuyo `desde` es menor que el ingreso
     * (en el borde, el de abajo); con ingreso cero, el primero.
     *
     * @return array{desde: int, numerador: int, indice: int}
     */
    public function tramoDe(Dinero $ingreso): array
    {
        $elegido = 0;

        foreach ($this->tramos as $i => $tramo) {
            if ($ingreso->centavos() > $tramo['desde'] * 100) {
                $elegido = $i;
            }
        }

        return [...$this->tramos[$elegido], 'indice' => $elegido];
    }

    /** `numerador / denominador` como texto ("0.22") para `Dinero::por()` y la traza. */
    public function tasaTexto(int $numerador): string
    {
        $digitos = strlen((string) $this->denominador) - 1;

        if ($digitos === 0) {
            return (string) $numerador;
        }

        $texto = str_pad((string) $numerador, $digitos + 1, '0', STR_PAD_LEFT);
        $entero = substr($texto, 0, -$digitos);
        $fraccion = substr($texto, -$digitos);

        return $entero.'.'.$fraccion;
    }

    /** Cociente redondeado mitad hacia arriba para valores no negativos. */
    public static function dividirRedondeando(int $numerador, int $divisor): int
    {
        return intdiv($numerador * 2 + $divisor, $divisor * 2);
    }
}
