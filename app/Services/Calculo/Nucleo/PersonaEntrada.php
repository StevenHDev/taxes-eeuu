<?php

namespace App\Services\Calculo\Nucleo;

use App\Enums\Expediente\ClasificacionPersona;

final readonly class PersonaEntrada
{
    public function __construct(
        public int $id,
        public RolPersona $rol,
        public ClasificacionPersona $clasificacion,
        public ?int $edadAlFinDeAnio,
        public bool $ssnValido,
        public bool $noValidoParaTrabajar,
    ) {}
}
