<?php

namespace App\Services\Calculo\Nucleo;

use InvalidArgumentException;

/**
 * Dinero en centavos enteros. En el motor de cálculo no se usa coma flotante:
 * ni para sumar, ni para multiplicar por un porcentaje, ni para redondear.
 */
final readonly class Dinero
{
    private function __construct(private int $centavos) {}

    public static function deCentavos(int $c): self
    {
        return new self($c);
    }

    /**
     * "1234.56" -> 123456. Acepta signo y hasta dos decimales; más decimales
     * serían centavos inventados, así que se rechazan.
     */
    public static function deDolares(int|string $d): self
    {
        if (is_int($d)) {
            return new self($d * 100);
        }

        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($d), $m) !== 1) {
            throw new InvalidArgumentException('Monto en dólares inválido.');
        }

        $centavos = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '', 2, '0');

        return new self($m[1] === '-' ? -$centavos : $centavos);
    }

    public function centavos(): int
    {
        return $this->centavos;
    }

    /**
     * Redondeo a dólares enteros según "Rounding Off to Whole Dollars" de las
     * instrucciones del Form 1040 de 2025 (i1040gi, p. 23): "You can round off
     * cents to whole dollars on your return and schedules. If you do round to
     * whole dollars, you must round all amounts. To round, drop amounts under
     * 50 cents and increase amounts from 50 to 99 cents to the next dollar."
     * Es opcional para el contribuyente; el motor lo ofrece y quien lo use
     * debe aplicarlo a todos los montos. Si hay que sumar varios montos, se
     * suman con centavos y se redondea solo el total ("round off only the
     * total"). Los negativos se redondean simétricos (la instrucción no los
     * cubre; por verificar con el IRS si algún caso lo exige).
     */
    public function aDolares(): self
    {
        $signo = $this->centavos < 0 ? -1 : 1;
        $dolares = intdiv(abs($this->centavos) + 50, 100);

        return new self($signo * $dolares * 100);
    }

    public function mas(self $o): self
    {
        return new self($this->centavos + $o->centavos);
    }

    public function menos(self $o): self
    {
        return new self($this->centavos - $o->centavos);
    }

    /**
     * Multiplica por un factor decimal escrito como texto ("0.9235") con
     * aritmética entera; el resultado se redondea al centavo (mitad hacia
     * afuera del cero).
     */
    public function por(string $factor): self
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', trim($factor), $m) !== 1) {
            throw new InvalidArgumentException('Factor decimal inválido.');
        }

        $decimales = $m[3] ?? '';
        $numerador = (int) ($m[2].$decimales);
        $denominador = 10 ** strlen($decimales);

        $producto = abs($this->centavos) * $numerador;
        $cociente = intdiv($producto * 2 + $denominador, $denominador * 2);
        $negativo = ($m[1] === '-') !== ($this->centavos < 0);

        return new self($negativo ? -$cociente : $cociente);
    }

    public function max(self $o): self
    {
        return $this->centavos >= $o->centavos ? $this : $o;
    }

    public function min(self $o): self
    {
        return $this->centavos <= $o->centavos ? $this : $o;
    }

    public function esNegativo(): bool
    {
        return $this->centavos < 0;
    }

    public function esCero(): bool
    {
        return $this->centavos === 0;
    }

    /** "1.234,56" (es). La presentación final la hace la vista. */
    public function texto(): string
    {
        $abs = abs($this->centavos);
        $enteros = (string) intdiv($abs, 100);
        $miles = strrev(implode('.', str_split(strrev($enteros), 3)));

        return ($this->centavos < 0 ? '-' : '').$miles.','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
