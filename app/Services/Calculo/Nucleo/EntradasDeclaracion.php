<?php

namespace App\Services\Calculo\Nucleo;

use App\Enums\Expediente\EstadoCivilIrs;

/** Solo la forma; la llena ArmadorEntradas (6.4i). */
final readonly class EntradasDeclaracion
{
    /**
     * @param  list<PersonaEntrada>  $personas
     * @param  array<string, DatoEntrada>  $datos  clave 'documento:casilla' o 'categoria:linea'
     * @param  array<string, Dinero>  $sumasRegistros  clave = línea del Schedule C
     * @param  array<string, RespuestaEntrada>  $respuestas  clave = código de pregunta
     */
    public function __construct(
        public int $taxYear,
        public ?EstadoCivilIrs $estadoCivil,
        public array $personas = [],
        public array $datos = [],
        public array $sumasRegistros = [],
        public array $respuestas = [],
    ) {}
}
