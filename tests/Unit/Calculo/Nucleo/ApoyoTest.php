<?php

use App\Enums\Expediente\ClasificacionPersona;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use Tests\TestCase;

// Los motivos usan __(): hace falta la aplicación (sin base de datos).
uses(TestCase::class);

// Los motivos se comparan en español; el idioma por defecto de la aplicación es inglés.
beforeEach(fn () => app()->setLocale('es'));

function lineaDe(string $f, string $l, string $dolares): Linea
{
    return Linea::calculada($f, $l, Dinero::deDolares($dolares), Traza::vacia());
}

it('suma líneas, cuenta las que entraron y no supone cero en una requerida que falta', function () {
    $d = new Declaracion(2025, 'v');
    $d->poner(lineaDe('sch-c', '31', '1000'));
    $d->poner(Linea::noAplica('sch-se', '13'));

    [$total, , , $aplicadas] = Apoyo::leerLineas($d, 'sch-1', '10', [['sch-c', '31', true], ['sch-se', '13', true], ['sch-1', '9', false]]);

    expect($total->centavos())->toBe(100000)->and($aplicadas)->toBe(1);

    $falta = Apoyo::leerLineas($d, 'sch-1', '10', [['sch-c', '31', true], ['sch-1', '8z', true]]);

    expect($falta)->toBeInstanceOf(Linea::class)
        ->and($falta->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($falta->formulario)->toBe('sch-1')
        ->and($falta->motivo)->toContain('8z')->toContain('Schedule 1');
});

it('da prioridad a falta_dato sobre no_soportada al leer varias líneas', function () {
    $d = new Declaracion(2025, 'v');
    $d->poner(Linea::noSoportada('sch-c', '31', 'x'));
    $d->poner(Linea::faltaDato('sch-se', '13', 'y'));

    expect(Apoyo::leerLineas($d, '1040', '10', [['sch-c', '31', true], ['sch-se', '13', true]])->estado)->toBe(EstadoLinea::FaltaDato);
});

it('copia una línea y hereda el no aplica con su motivo', function () {
    $d = new Declaracion(2025, 'v');
    $d->poner(lineaDe('sch-c', '31', '250.50'));
    $d->poner(Linea::noAplica('sch-se', '13', 'Sin autoempleo'));

    Apoyo::copiar($d, 'sch-1', '3', 'sch-c', '31');
    Apoyo::copiar($d, 'sch-1', '15', 'sch-se', '13');

    expect($d->linea('sch-1', '3')->valor->centavos())->toBe(25050)
        ->and($d->linea('sch-1', '15')->estado)->toBe(EstadoLinea::NoAplica)
        ->and($d->linea('sch-1', '15')->motivo)->toBe('Sin autoempleo');
});

it('corta en la primera línea que no sirve y hereda su motivo', function () {
    $d = new Declaracion(2025, 'v');
    $d->poner(lineaDe('1040', '16', '100'));
    $d->poner(Linea::noSoportada('sch-2', '3', 'Falta la hoja QDCG'));

    Apoyo::sumar($d, '1040', '18', [['1040', '16'], ['sch-2', '3']], 'Línea 18');

    expect($d->linea('1040', '18')->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($d->linea('1040', '18')->motivo)->toBe('Falta la hoja QDCG');
});

it('lee los documentos uno por uno y sabe de quién es cada uno', function () {
    $w2 = new DocumentoEntrada('w2', 7, 2, [
        '1' => new DatoEntrada(Dinero::deDolares('40000'), OrigenTraza::Leido, 'dato:1'),
        'c' => new DatoEntrada('Empresa', OrigenTraza::Leido, 'dato:2'),
    ]);
    $e = new EntradasDeclaracion(2025, null, [
        new PersonaEntrada(2, RolPersona::Conyuge, ClasificacionPersona::NoDependiente, 40, true, false),
    ], documentos: ['w2' => [$w2]]);

    expect($e->documentos('w2'))->toHaveCount(1)
        ->and($e->documentos('1095-a'))->toBe([])
        ->and($w2->monto('1')?->centavos())->toBe(4000000)
        ->and($w2->monto('c'))->toBeNull()
        ->and($w2->monto('99'))->toBeNull()
        ->and($w2->referencia())->toBe('w2#7')
        ->and(Apoyo::personaPorId($e, $w2->personaId)?->rol)->toBe(RolPersona::Conyuge)
        ->and(Apoyo::persona($e, RolPersona::Contribuyente))->toBeNull()
        ->and(Apoyo::hayDatos($e, 'w2'))->toBeTrue()
        ->and(Apoyo::documentosPresentes($e, ['1099-int', 'w2']))->toBe(['w2']);
});

it('arma las referencias de lo que entra sin validar, nunca el valor', function () {
    $datos = [
        'w2:1' => new DatoEntrada(Dinero::deDolares('10'), OrigenTraza::Leido, 'dato:9', validado: false),
        'w2:2' => new DatoEntrada(Dinero::deDolares('5'), OrigenTraza::Leido),
        'w2:5' => new DatoEntrada(Dinero::deDolares('5'), OrigenTraza::Leido, conExcepcion: true),
    ];

    expect(Apoyo::sinValidar($datos))->toBe(['dato:9', 'w2:5']);
});

it('dice qué parámetro falta cuando viene en el mensaje', function () {
    expect(Apoyo::motivoSinParametro(2026, new RuntimeException('Pendiente [schedule_c.millaje_estandar]')))
        ->toContain('schedule_c.millaje_estandar')->toContain('2026')
        ->and(Apoyo::motivoSinParametro(2026, new RuntimeException('otra cosa')))->toContain('un parámetro de 2026');
});
