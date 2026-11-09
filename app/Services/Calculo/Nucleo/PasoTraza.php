<?php

namespace App\Services\Calculo\Nucleo;

use InvalidArgumentException;

final readonly class PasoTraza
{
    public function __construct(
        public TipoPaso $tipo,
        public string $texto,
        public Dinero|bool|string|null $valor = null,
        public ?OrigenTraza $origen = null,
        public ?string $referencia = null,
    ) {
        // La referencia es un id, un código o una clave; un valor con forma
        // de SSN/TIN indica que se coló un dato sensible (§7216).
        if ($referencia !== null && preg_match('/\d{3}-?\d{2}-?\d{4}/', $referencia) === 1) {
            throw new InvalidArgumentException('La referencia de la traza no puede contener un valor sensible.');
        }
    }
}
