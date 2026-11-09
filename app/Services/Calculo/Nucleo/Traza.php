<?php

namespace App\Services\Calculo\Nucleo;

/** Inmutable: cada método devuelve una traza nueva. */
final readonly class Traza
{
    /** @param list<PasoTraza> $pasos */
    private function __construct(public array $pasos) {}

    public static function vacia(): self
    {
        return new self([]);
    }

    public function conEntrada(string $texto, Dinero|bool|string|null $valor, OrigenTraza $origen, ?string $referencia = null): self
    {
        return $this->con(new PasoTraza(TipoPaso::Entrada, $texto, $valor, $origen, $referencia));
    }

    public function conParametro(string $texto, Dinero|bool|string|null $valor, ?string $referencia = null): self
    {
        return $this->con(new PasoTraza(TipoPaso::Parametro, $texto, $valor, OrigenTraza::Parametro, $referencia));
    }

    public function conOperacion(string $texto, Dinero|bool|string|null $valor = null): self
    {
        return $this->con(new PasoTraza(TipoPaso::Operacion, $texto, $valor));
    }

    public function conResultado(string $texto, Dinero|bool|string|null $valor): self
    {
        return $this->con(new PasoTraza(TipoPaso::Resultado, $texto, $valor));
    }

    private function con(PasoTraza $paso): self
    {
        return new self([...$this->pasos, $paso]);
    }
}
