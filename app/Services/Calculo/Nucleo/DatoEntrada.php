<?php

namespace App\Services\Calculo\Nucleo;

final readonly class DatoEntrada
{
    public function __construct(
        public Dinero|string|null $valor,
        public OrigenTraza $origen,
        public ?string $referencia = null,
        public bool $validado = true,
        public bool $conExcepcion = false,
    ) {}

    /** Entra al cálculo igual, pero la línea que lo usa lleva la marca "usa datos sin validar". */
    public function usaSinValidar(): bool
    {
        return ! $this->validado || $this->conExcepcion;
    }
}
