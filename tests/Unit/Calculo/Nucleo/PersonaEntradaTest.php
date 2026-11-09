<?php

use App\Enums\Expediente\ClasificacionPersona;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;

it('construye con los seis datos de siempre y deja en null los opcionales del EIC', function () {
    $p = new PersonaEntrada(1, RolPersona::Dependiente, ClasificacionPersona::HijoCalificado, 8, true, false);

    expect($p->id)->toBe(1)->and($p->edadAlFinDeAnio)->toBe(8)->and($p->ssnValido)->toBeTrue()
        ->and($p->esEstudiante)->toBeNull()
        ->and($p->incapacitado)->toBeNull()
        ->and($p->mesesConviviendo)->toBeNull()
        ->and($p->parentesco)->toBeNull()
        ->and($p->otroPuedeReclamar)->toBeNull();
});

it('guarda los datos opcionales del bloque C que usa el EIC', function () {
    $p = new PersonaEntrada(2, RolPersona::Dependiente, ClasificacionPersona::HijoCalificado, 19, true, false, true, false, 12, 'hijo', false);

    expect($p->esEstudiante)->toBeTrue()
        ->and($p->incapacitado)->toBeFalse()
        ->and($p->mesesConviviendo)->toBe(12)
        ->and($p->parentesco)->toBe('hijo')
        ->and($p->otroPuedeReclamar)->toBeFalse();
});

it('es inmutable', function () {
    $p = new PersonaEntrada(3, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, 40, true, false);

    expect(fn () => $p->parentesco = 'hijo')->toThrow(Error::class);
});
