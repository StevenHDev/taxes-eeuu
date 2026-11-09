<?php

namespace App\Services\Calculo\Nucleo;

/** Respuesta de la entrevista que usa el cálculo (la forma exacta la fija 6.4i si hace falta). */
final readonly class RespuestaEntrada
{
    public function __construct(
        public Dinero|bool|string|null $valor,
        public OrigenTraza $origen = OrigenTraza::Respuesta,
        public ?string $referencia = null,
    ) {}
}
