<?php

namespace App\Services\Calculo\Nucleo;

use App\Enums\Expediente\ClasificacionPersona;

final readonly class PersonaEntrada
{
    /**
     * Las cinco últimas las llena ArmadorEntradas (6.4i) desde la entrevista (bloque C) y solo aplican
     * a dependientes; `null` = "sin dato" y la calculadora que lo necesite escribe falta_dato.
     *
     * @param  ?bool  $esEstudiante  C7: estudiante a tiempo completo (dependientes). En el contribuyente y el cónyuge lo usa el 2441 si no tuvieron ingreso del trabajo; hoy no se pregunta (null)
     * @param  ?bool  $incapacitado  C8: incapacitado total y permanentemente (dependientes; contribuyente y cónyuge, igual que arriba)
     * @param  ?int  $mesesConviviendo  C4: meses que vivió con el contribuyente en EE. UU. durante el año
     * @param  ?string  $parentesco  C3: valor de la respuesta (hijo, hijastro, hijo_crianza, hermano, nieto, padre, ninguno)
     * @param  ?bool  $otroPuedeReclamar  C11: otra persona también puede reclamarlo
     */
    public function __construct(
        public int $id,
        public RolPersona $rol,
        public ClasificacionPersona $clasificacion,
        public ?int $edadAlFinDeAnio,
        public bool $ssnValido,
        public bool $noValidoParaTrabajar,
        public ?bool $esEstudiante = null,
        public ?bool $incapacitado = null,
        public ?int $mesesConviviendo = null,
        public ?string $parentesco = null,
        public ?bool $otroPuedeReclamar = null,
    ) {}
}
