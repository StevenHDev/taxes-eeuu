<?php

use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\Traza;

it('crea una línea calculada con su valor y la marca de datos sin validar', function () {
    $l = Linea::calculada('1040', '11a', Dinero::deDolares('45000'), Traza::vacia(), ['dato:7']);

    expect($l->estado)->toBe(EstadoLinea::Calculada)
        ->and($l->valor->centavos())->toBe(4500000)
        ->and($l->usaSinValidar)->toBeTrue()
        ->and($l->datosSinValidar)->toBe(['dato:7'])
        ->and(Linea::calculada('1040', '1a', true, Traza::vacia())->usaSinValidar)->toBeFalse();
});

it('falta_dato, no_soportada y no_aplica no tienen valor y llevan motivo', function () {
    $f = Linea::faltaDato('1040', '16', 'Falta la línea 15');
    $s = Linea::noSoportada('1040', '12e', 'Deducción detallada');
    $n = Linea::noAplica('1040', '12d');

    expect($f->valor)->toBeNull()->and($f->estado)->toBe(EstadoLinea::FaltaDato)->and($f->motivo)->toBe('Falta la línea 15')
        ->and($s->valor)->toBeNull()->and($s->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($n->valor)->toBeNull()->and($n->estado)->toBe(EstadoLinea::NoAplica);
});

it('no permite valor en una línea que no está calculada', function () {
    new Linea('1040', '16', Dinero::deDolares('1'), EstadoLinea::FaltaDato, 'x', Traza::vacia());
})->throws(InvalidArgumentException::class);

it('no permite una línea calculada sin valor', function () {
    new Linea('1040', '16', null, EstadoLinea::Calculada, null, Traza::vacia());
})->throws(InvalidArgumentException::class);
