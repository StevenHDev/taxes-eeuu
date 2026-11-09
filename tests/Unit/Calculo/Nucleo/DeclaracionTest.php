<?php

use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\Traza;

function calc(string $f, string $l): Linea
{
    return Linea::calculada($f, $l, Dinero::deDolares('1'), Traza::vacia());
}

it('guarda y busca líneas por formulario y línea', function () {
    $d = new Declaracion(2025, '2025.6');
    $d->poner(calc('1040', '1a'));
    $d->poner(calc('sch-c', '31'));
    $d->poner(calc('1040', '11a'));

    expect($d->taxYear)->toBe(2025)->and($d->versionReglas)->toBe('2025.6')
        ->and($d->linea('1040', '11a')?->linea)->toBe('11a')
        ->and($d->linea('1040', '99'))->toBeNull()
        ->and($d->formularios())->toBe(['1040', 'sch-c'])
        ->and($d->lineas())->toHaveCount(3)
        ->and($d->lineas('1040'))->toHaveCount(2)
        ->and($d->lineas('nada'))->toBe([]);
});

it('reemplaza una línea escrita dos veces', function () {
    $d = new Declaracion(2025, 'v');
    $d->poner(Linea::faltaDato('1040', '16', 'x'));
    $d->poner(calc('1040', '16'));

    expect($d->lineas())->toHaveCount(1)->and($d->estadoGeneral())->toBe('completa');
});

it('es completa si todo está calculado o no aplica', function () {
    $d = new Declaracion(2025, 'v');
    $d->poner(calc('1040', '1a'));
    $d->poner(Linea::noAplica('1040', '12d'));

    expect($d->estadoGeneral())->toBe('completa');
});

it('es parcial si hay una no soportada y nada falta', function () {
    $d = new Declaracion(2025, 'v');
    $d->poner(calc('1040', '1a'));
    $d->poner(Linea::noSoportada('1040', '12e', 'x'));

    expect($d->estadoGeneral())->toBe('parcial');
});

it('es incompleta si falta un dato, aunque haya también una no soportada', function () {
    $d = new Declaracion(2025, 'v');
    $d->poner(calc('1040', '1a'));
    $d->poner(Linea::noSoportada('1040', '12e', 'x'));
    $d->poner(Linea::faltaDato('1040', '16', 'x'));

    expect($d->estadoGeneral())->toBe('incompleta');
});

it('una declaración sin líneas es incompleta', function () {
    expect((new Declaracion(2025, 'v'))->estadoGeneral())->toBe('incompleta');
});
