<?php

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Schedule1A;
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
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Valores esperados armados a mano con el Schedule 1-A de 2025 (f1040s1a--2025.pdf) y sus
 * instrucciones, que en 2025 están dentro de las del 1040 (i1040gi--2025.pdf, p. 101 a 110):
 * - Parte II (p. 1): 7 = menor entre 6 y USD 25.000; 9 = USD 150.000 (300.000 conjunta);
 *   10 = 8 − 9 (si es cero o menos, 13 = 7); 11 = 10 ÷ 1.000 redondeado hacia ABAJO;
 *   12 = 11 × USD 100; 13 = 7 − 12 (mínimo cero).
 * - Parte III (p. 1): 15 = menor entre 14c y USD 12.500 (25.000 conjunta); 16 a 21 igual que
 *   la Parte II.
 * - Parte IV (p. 2; instrucciones p. 109): el vehículo usado no califica; exige ensamblaje
 *   final en EE. UU.
 * - Parte V (p. 2; instrucciones p. 110): 32 = USD 75.000 (150.000 conjunta); 34 = 33 × 6 %;
 *   35 = USD 6.000 − 34; con los dos cónyuges de 65+ y SSN válido, hasta USD 12.000.
 * - "If married, you must file jointly to claim this deduction" (Partes II, III y V).
 * - SSN válido = "valid for employment" (instrucciones p. 104 y 108).
 */

beforeEach(function () {
    // Los motivos se comparan en español; el idioma por defecto de la aplicación es inglés.
    app()->setLocale('es');
    $this->seed(ParametrosFiscalesSeeder::class);
});

function s1a_persona(int $id, RolPersona $rol, ?int $edad = 40, bool $ssnValido = true, bool $noValidoParaTrabajar = false): PersonaEntrada
{
    return new PersonaEntrada($id, $rol, ClasificacionPersona::NoDependiente, $edad, $ssnValido, $noValidoParaTrabajar);
}

/** @param array<string, string> $montos casilla => dólares */
function s1a_constancia(int $item, ?int $personaId, array $montos, bool $validado = true): DocumentoEntrada
{
    $casillas = [];

    foreach ($montos as $casilla => $dolares) {
        $casillas[$casilla] = new DatoEntrada(Dinero::deDolares($dolares), OrigenTraza::Leido, "constancia-horas-propinas#{$item}:{$casilla}", $validado);
    }

    return new DocumentoEntrada('constancia-horas-propinas', $item, $personaId, $casillas);
}

function s1a_prestamo(int $item, ?string $nuevo, string $intereses = '1200'): DocumentoEntrada
{
    $casillas = ['intereses-pagados' => new DatoEntrada(Dinero::deDolares($intereses), OrigenTraza::Leido, "estado-prestamo-vehiculo#{$item}:intereses-pagados")];

    if ($nuevo !== null) {
        $casillas['vehiculo-nuevo'] = new DatoEntrada($nuevo, OrigenTraza::Leido, "estado-prestamo-vehiculo#{$item}:vehiculo-nuevo");
    }

    return new DocumentoEntrada('estado-prestamo-vehiculo', $item, 1, $casillas);
}

/**
 * @param  list<PersonaEntrada>|null  $personas
 * @param  array<string, list<DocumentoEntrada>>  $documentos
 */
function s1a_entradas(?EstadoCivilIrs $estado = EstadoCivilIrs::Single, ?array $personas = null, array $documentos = [], int $anio = 2025): EntradasDeclaracion
{
    $personas ??= [s1a_persona(1, RolPersona::Contribuyente)];

    return new EntradasDeclaracion($anio, $estado, personas: $personas, documentos: $documentos);
}

function s1a_declaracion(?string $agi = '100000', int $anio = 2025): Declaracion
{
    $d = new Declaracion($anio, 'f6-prueba');

    if ($agi !== null) {
        $d->poner(Linea::calculada('1040', '11b', Dinero::deDolares($agi), Traza::vacia()));
    }

    return $d;
}

function s1a_calcular(EntradasDeclaracion $e, Declaracion $d): Declaracion
{
    (new Schedule1A)->calcular($e, $d);

    return $d;
}

function s1a_valor(Declaracion $d, string $linea): Dinero|bool|string|null
{
    $l = $d->linea('sch-1a', $linea);

    return $l?->valor instanceof Dinero ? $l->valor->centavos() / 100 : $l?->valor;
}

function s1a_estado(Declaracion $d, string $linea): ?EstadoLinea
{
    return $d->linea('sch-1a', $linea)?->estado;
}

it('escribe el código de formulario sch-1a', function () {
    expect((new Schedule1A)->formulario())->toBe('sch-1a');
});

it('sin constancias, préstamo ni mayores la línea 38 no aplica y no escribe el MAGI', function () {
    $d = s1a_calcular(s1a_entradas(), s1a_declaracion());

    expect(s1a_estado($d, '38'))->toBe(EstadoLinea::NoAplica)
        ->and(s1a_estado($d, '13'))->toBe(EstadoLinea::NoAplica)
        ->and(s1a_estado($d, '21'))->toBe(EstadoLinea::NoAplica)
        ->and(s1a_estado($d, '30'))->toBe(EstadoLinea::NoAplica)
        ->and(s1a_estado($d, '37'))->toBe(EstadoLinea::NoAplica)
        ->and($d->linea('sch-1a', '1'))->toBeNull()
        ->and($d->linea('sch-1a', '3'))->toBeNull();
});

it('propinas por encima del tope y sin reducción: la 13 es el tope', function () {
    // 4c = 6 = 30.000; 7 = min(30.000; 25.000) = 25.000; MAGI 100.000 − 150.000 < 0 → 13 = 7 = 25.000.
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '30000'])]]);
    $d = s1a_calcular($e, s1a_declaracion('100000'));

    expect(s1a_valor($d, '1'))->toEqual(100000)
        ->and(s1a_valor($d, '3'))->toEqual(100000)
        ->and(s1a_valor($d, '4c'))->toEqual(30000)
        ->and(s1a_valor($d, '6'))->toEqual(30000)
        ->and(s1a_valor($d, '7'))->toEqual(25000)
        ->and(s1a_valor($d, '9'))->toEqual(150000)
        ->and(s1a_valor($d, '10'))->toEqual(-50000)
        ->and($d->linea('sch-1a', '11'))->toBeNull()
        ->and($d->linea('sch-1a', '12'))->toBeNull()
        ->and(s1a_valor($d, '13'))->toEqual(25000)
        ->and(s1a_valor($d, '38'))->toEqual(25000);
});

it('propinas con reducción por MAGI: el exceso dividido entre 1.000 se redondea hacia abajo', function () {
    // MAGI 160.500: 10 = 10.500; 11 = 10 (10,5 baja a 10); 12 = 10 × 100 = 1.000; 13 = 25.000 − 1.000 = 24.000.
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '30000'])]]);
    $d = s1a_calcular($e, s1a_declaracion('160500'));

    expect(s1a_valor($d, '10'))->toEqual(10500)
        ->and(s1a_valor($d, '11'))->toBe('10')
        ->and(s1a_valor($d, '12'))->toEqual(1000)
        ->and(s1a_valor($d, '13'))->toEqual(24000);
});

it('propinas: un exceso de menos de USD 1.000 no reduce nada (0,99 baja a 0)', function () {
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '8000'])]]);
    $d = s1a_calcular($e, s1a_declaracion('150999.99'));

    expect(s1a_valor($d, '11'))->toBe('0')
        ->and(s1a_valor($d, '12'))->toEqual(0)
        ->and(s1a_valor($d, '13'))->toEqual(8000);
});

it('propinas en conjunta: el umbral es USD 300.000 y cuentan las del cónyuge', function () {
    $personas = [s1a_persona(1, RolPersona::Contribuyente), s1a_persona(2, RolPersona::Conyuge)];
    $e = s1a_entradas(EstadoCivilIrs::Mfj, $personas, ['constancia-horas-propinas' => [
        s1a_constancia(7, 1, ['propinas-calificadas' => '6000']),
        s1a_constancia(8, 2, ['propinas-calificadas' => '4000']),
    ]]);
    $d = s1a_calcular($e, s1a_declaracion('250000'));

    expect(s1a_valor($d, '4c'))->toEqual(10000)
        ->and(s1a_valor($d, '9'))->toEqual(300000)
        ->and(s1a_valor($d, '13'))->toEqual(10000);
});

it('propinas con MFS no aplican: el Schedule 1-A exige declarar en conjunto', function () {
    $e = s1a_entradas(EstadoCivilIrs::Mfs, documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '6000'])]]);
    $d = s1a_calcular($e, s1a_declaracion());

    expect(s1a_estado($d, '13'))->toBe(EstadoLinea::NoAplica)
        ->and($d->linea('sch-1a', '13')->motivo)->toContain('MFS')
        ->and(s1a_estado($d, '38'))->toBe(EstadoLinea::NoAplica);
});

it('las propinas de quien no tiene SSN válido para trabajar no cuentan', function () {
    $e = s1a_entradas(personas: [s1a_persona(1, RolPersona::Contribuyente, noValidoParaTrabajar: true)], documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '6000'])]]);
    $d = s1a_calcular($e, s1a_declaracion());

    expect(s1a_valor($d, '4c'))->toEqual(0)
        ->and(s1a_valor($d, '13'))->toEqual(0);
});

it('una constancia sin dueño deja la parte en falta_dato', function () {
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, null, ['propinas-calificadas' => '6000'])]]);
    $d = s1a_calcular($e, s1a_declaracion());

    expect(s1a_estado($d, '13'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1a', '13')->motivo)->toBe('Falta saber de quién es la constancia constancia-horas-propinas#7.')
        ->and(s1a_estado($d, '38'))->toBe(EstadoLinea::FaltaDato);
});

it('horas extra de una persona soltera: tope de USD 12.500 y reducción por MAGI', function () {
    // 14c = 20.000; 15 = 12.500; 18 = 151.999 − 150.000 = 1.999; 19 = 1; 20 = 100; 21 = 12.400.
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['horas-extra-calificadas' => '20000'])]]);
    $d = s1a_calcular($e, s1a_declaracion('151999'));

    expect(s1a_valor($d, '14c'))->toEqual(20000)
        ->and(s1a_valor($d, '15'))->toEqual(12500)
        ->and(s1a_valor($d, '16'))->toEqual(151999)
        ->and(s1a_valor($d, '17'))->toEqual(150000)
        ->and(s1a_valor($d, '18'))->toEqual(1999)
        ->and(s1a_valor($d, '19'))->toBe('1')
        ->and(s1a_valor($d, '20'))->toEqual(100)
        ->and(s1a_valor($d, '21'))->toEqual(12400)
        ->and(s1a_estado($d, '13'))->toBe(EstadoLinea::NoAplica)
        ->and(s1a_valor($d, '38'))->toEqual(12400);
});

it('horas extra en conjunta: el tope es USD 25.000', function () {
    $personas = [s1a_persona(1, RolPersona::Contribuyente), s1a_persona(2, RolPersona::Conyuge)];
    $e = s1a_entradas(EstadoCivilIrs::Mfj, $personas, ['constancia-horas-propinas' => [s1a_constancia(7, 2, ['horas-extra-calificadas' => '27000'])]]);
    $d = s1a_calcular($e, s1a_declaracion('200000'));

    expect(s1a_valor($d, '15'))->toEqual(25000)
        ->and(s1a_valor($d, '21'))->toEqual(25000);
});

it('el vehículo usado no da la deducción', function () {
    $e = s1a_entradas(documentos: ['estado-prestamo-vehiculo' => [s1a_prestamo(9, 'no')]]);
    $d = s1a_calcular($e, s1a_declaracion());

    expect(s1a_estado($d, '30'))->toBe(EstadoLinea::NoAplica)
        ->and(s1a_estado($d, '38'))->toBe(EstadoLinea::NoAplica);
});

it('el vehículo nuevo queda en falta_dato: falta confirmar el ensamblaje final en EE. UU.', function () {
    $e = s1a_entradas(documentos: ['estado-prestamo-vehiculo' => [s1a_prestamo(9, 'si')]]);
    $d = s1a_calcular($e, s1a_declaracion());

    expect(s1a_estado($d, '30'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1a', '30')->motivo)->toContain('ensamblaje final en EE. UU.')
        ->and(s1a_estado($d, '38'))->toBe(EstadoLinea::FaltaDato);
});

it('sin saber si el vehículo es nuevo, la parte IV queda en falta_dato', function () {
    $e = s1a_entradas(documentos: ['estado-prestamo-vehiculo' => [s1a_prestamo(9, null)]]);
    $d = s1a_calcular($e, s1a_declaracion());

    expect(s1a_estado($d, '30'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1a', '30')->motivo)->toBe('Falta confirmar si el vehículo del préstamo estado-prestamo-vehiculo#9 es nuevo.');
});

it('mayores: soltero de 66 años con MAGI de USD 80.000', function () {
    // 33 = 80.000 − 75.000 = 5.000; 34 = 5.000 × 6 % = 300; 35 = 6.000 − 300 = 5.700; 36a = 37 = 5.700.
    $e = s1a_entradas(personas: [s1a_persona(1, RolPersona::Contribuyente, 66)]);
    $d = s1a_calcular($e, s1a_declaracion('80000'));

    expect(s1a_valor($d, '31'))->toEqual(80000)
        ->and(s1a_valor($d, '32'))->toEqual(75000)
        ->and(s1a_valor($d, '33'))->toEqual(5000)
        ->and(s1a_valor($d, '34'))->toEqual(300)
        ->and(s1a_valor($d, '35'))->toEqual(5700)
        ->and(s1a_valor($d, '36a'))->toEqual(5700)
        ->and(s1a_estado($d, '36b'))->toBe(EstadoLinea::NoAplica)
        ->and(s1a_valor($d, '37'))->toEqual(5700)
        ->and(s1a_valor($d, '38'))->toEqual(5700);
});

it('mayores: los dos cónyuges de 65 o más bajo el umbral suman USD 12.000', function () {
    // Instrucciones p. 110: "the maximum amount of the enhanced deduction for seniors is $12,000".
    $personas = [s1a_persona(1, RolPersona::Contribuyente, 70), s1a_persona(2, RolPersona::Conyuge, 67)];
    $d = s1a_calcular(s1a_entradas(EstadoCivilIrs::Mfj, $personas), s1a_declaracion('100000'));

    expect(s1a_valor($d, '32'))->toEqual(150000)
        ->and($d->linea('sch-1a', '34'))->toBeNull()
        ->and(s1a_valor($d, '35'))->toEqual(6000)
        ->and(s1a_valor($d, '36a'))->toEqual(6000)
        ->and(s1a_valor($d, '36b'))->toEqual(6000)
        ->and(s1a_valor($d, '37'))->toEqual(12000);
});

it('mayores: el cónyuge sin SSN válido para trabajar no cuenta', function () {
    $personas = [s1a_persona(1, RolPersona::Contribuyente, 70), s1a_persona(2, RolPersona::Conyuge, 67, ssnValido: false)];
    $d = s1a_calcular(s1a_entradas(EstadoCivilIrs::Mfj, $personas), s1a_declaracion('100000'));

    expect(s1a_valor($d, '36a'))->toEqual(6000)
        ->and(s1a_estado($d, '36b'))->toBe(EstadoLinea::NoAplica)
        ->and(s1a_valor($d, '37'))->toEqual(6000);
});

it('mayores: con MAGI muy alto la deducción llega a cero', function () {
    // 33 = 175.000 − 75.000 = 100.000; 34 = 6.000; 35 = 0.
    $d = s1a_calcular(s1a_entradas(personas: [s1a_persona(1, RolPersona::Contribuyente, 66)]), s1a_declaracion('175000'));

    expect(s1a_valor($d, '35'))->toEqual(0)
        ->and(s1a_valor($d, '37'))->toEqual(0);
});

it('mayores con MFS no aplican', function () {
    $d = s1a_calcular(s1a_entradas(EstadoCivilIrs::Mfs, [s1a_persona(1, RolPersona::Contribuyente, 70)]), s1a_declaracion('50000'));

    expect(s1a_estado($d, '37'))->toBe(EstadoLinea::NoAplica)
        ->and($d->linea('sch-1a', '37')->motivo)->toContain('MFS');
});

it('mayores: con 64 años al cierre el preparador confirma la fecha de nacimiento', function () {
    $d = s1a_calcular(s1a_entradas(personas: [s1a_persona(1, RolPersona::Contribuyente, 64)]), s1a_declaracion('50000'));

    expect(s1a_estado($d, '37'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1a', '37')->motivo)->toContain('64 años');
});

it('sin la línea 11b del 1040 las partes con datos quedan en falta_dato', function () {
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '3000'])]]);
    $d = s1a_calcular($e, s1a_declaracion(null));

    expect(s1a_estado($d, '1'))->toBe(EstadoLinea::FaltaDato)
        ->and(s1a_estado($d, '13'))->toBe(EstadoLinea::FaltaDato)
        ->and(s1a_estado($d, '38'))->toBe(EstadoLinea::FaltaDato);
});

it('una constancia sin validar marca la deducción y la línea 38 como "usa datos sin validar"', function () {
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '3000'], validado: false)]]);
    $d = s1a_calcular($e, s1a_declaracion());

    expect($d->linea('sch-1a', '13')->usaSinValidar)->toBeTrue()
        ->and($d->linea('sch-1a', '13')->datosSinValidar)->toBe(['constancia-horas-propinas#7:propinas-calificadas'])
        ->and($d->linea('sch-1a', '38')->usaSinValidar)->toBeTrue();
});

it('la traza lleva referencias y nunca montos', function () {
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '3000'])]]);
    $d = s1a_calcular($e, s1a_declaracion());

    foreach ($d->lineas('sch-1a') as $linea) {
        foreach ($linea->traza->pasos as $paso) {
            expect($paso->referencia === null || ! str_contains($paso->referencia, '3000'))->toBeTrue();
        }
    }

    $referencias = array_map(fn ($p) => $p->referencia, $d->linea('sch-1a', '4c')->traza->pasos);
    expect($referencias)->toContain('constancia-horas-propinas#7:propinas-calificadas');
});

it('en 2026 sin parámetros cargados la parte queda en falta_dato con el nombre del parámetro', function () {
    $e = s1a_entradas(documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '3000'])]], anio: 2026);
    $d = s1a_calcular($e, s1a_declaracion('50000', 2026));

    expect(s1a_estado($d, '13'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1a', '13')->motivo)->toBe('No se puede calcular: falta el parámetro schedule_1a.propinas_tope de 2026 (Catálogo → Parámetros).');
});

it('sin estado civil las partes con datos quedan en falta_dato', function () {
    $e = s1a_entradas(null, documentos: ['constancia-horas-propinas' => [s1a_constancia(7, 1, ['propinas-calificadas' => '3000'])]]);
    $d = s1a_calcular($e, s1a_declaracion());

    expect(s1a_estado($d, '13'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1a', '13')->motivo)->toBe('Falta decidir el estado civil en Revisión.')
        ->and(s1a_estado($d, '37'))->toBe(EstadoLinea::FaltaDato);
});
