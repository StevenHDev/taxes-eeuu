<?php

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Ingresos1040;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\RespuestaEntrada;
use App\Services\Calculo\Nucleo\Traza;
use Tests\TestCase;

uses(TestCase::class);

// Los motivos se comparan en español; el idioma por defecto de la aplicación es inglés.
beforeEach(fn () => app()->setLocale('es'));

/*
 * Valores esperados: aritmética de las líneas del Form 1040 de 2025, p. 1
 * (https://www.irs.gov/pub/irs-prior/f1040--2025.pdf):
 *   1z  "Add lines 1a through 1h"
 *   8   "Additional income from Schedule 1, line 10"
 *   9   "Add lines 1z, 2b, 3b, 4b, 5b, 6b, 7a, and 8. This is your total income"
 *   10  "Adjustments to income from Schedule 1, line 26"
 *   11a "Subtract line 10 from line 9. This is your adjusted gross income"
 * y p. 2, línea 11b: "Amount from line 11a".
 * Propinas asignadas (W-2 casilla 8) a la línea 1c: instrucciones del 1040 de 2025, p. 22, "Line 1c".
 */

function ingresos1040_dato(string $dolares, string $referencia, bool $validado = true, OrigenTraza $origen = OrigenTraza::Leido): DatoEntrada
{
    return new DatoEntrada(Dinero::deDolares($dolares), $origen, $referencia, $validado);
}

/** @param array<string, DatoEntrada> $datos */
function ingresos1040_entradas(array $datos = [], array $respuestas = []): EntradasDeclaracion
{
    return new EntradasDeclaracion(2025, EstadoCivilIrs::Single, datos: $datos, respuestas: $respuestas);
}

function ingresos1040_declaracion(?string $linea10 = '0', ?string $linea26 = '0'): Declaracion
{
    $d = new Declaracion(2025, 'f6-prueba');

    if ($linea10 !== null) {
        $d->poner(Linea::calculada('sch-1', '10', Dinero::deDolares($linea10), Traza::vacia()));
    }
    if ($linea26 !== null) {
        $d->poner(Linea::calculada('sch-1', '26', Dinero::deDolares($linea26), Traza::vacia()));
    }

    return $d;
}

function ingresos1040_valor(Declaracion $d, string $linea): ?int
{
    return $d->linea('1040', $linea)?->valor?->centavos();
}

it('escribe del 1040 el código de formulario 1040', function () {
    expect((new Ingresos1040)->formulario())->toBe('1040');
});

it('la 1a es la suma de la casilla 1 de los W-2 y su traza dice de dónde sale', function () {
    // Dos W-2: 52.000,00 + 33.000,50 = 85.000,50 (ya sumados en el dato).
    $d = ingresos1040_declaracion();
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('85000.50', 'w2:1')]), $d);

    $l = $d->linea('1040', '1a');

    expect($l->estado)->toBe(EstadoLinea::Calculada)
        ->and($l->valor->centavos())->toBe(8500050)
        ->and($l->usaSinValidar)->toBeFalse()
        ->and($l->traza->pasos[0]->origen)->toBe(OrigenTraza::Leido)
        ->and($l->traza->pasos[0]->referencia)->toBe('w2:1');
});

it('sin W-2 la 1a no aplica y la 1z vale cero', function () {
    $d = ingresos1040_declaracion();
    (new Ingresos1040)->calcular(ingresos1040_entradas(), $d);

    expect($d->linea('1040', '1a')->estado)->toBe(EstadoLinea::NoAplica)
        ->and(ingresos1040_valor($d, '1z'))->toBe(0);
});

it('un W-2 sin monto deja la 1a y todo lo que depende de ella en falta_dato', function () {
    $d = ingresos1040_declaracion();
    $dato = new DatoEntrada(null, OrigenTraza::Leido, 'w2:1');
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => $dato]), $d);

    expect($d->linea('1040', '1a')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '1a')->valor)->toBeNull();
});

it('encadena 1z, 8, 9, 10, 11a y 11b con las líneas de Schedule 1', function () {
    // W-2: 85.000,00. Schedule 1: línea 10 = 20.000,00 y línea 26 = 1.413,00.
    // 1z = 85.000; 8 = 20.000; 9 = 105.000; 10 = 1.413; 11a = 105.000 - 1.413 = 103.587; 11b = 11a.
    $d = ingresos1040_declaracion('20000', '1413');
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('85000', 'w2:1')]), $d);

    expect(ingresos1040_valor($d, '1a'))->toBe(8500000)
        ->and(ingresos1040_valor($d, '1z'))->toBe(8500000)
        ->and(ingresos1040_valor($d, '8'))->toBe(2000000)
        ->and(ingresos1040_valor($d, '9'))->toBe(10500000)
        ->and(ingresos1040_valor($d, '10'))->toBe(141300)
        ->and(ingresos1040_valor($d, '11a'))->toBe(10358700)
        ->and(ingresos1040_valor($d, '11b'))->toBe(10358700)
        ->and($d->estadoGeneral())->toBe('completa');
});

it('mantiene el cero y el negativo tal cual en la 9 y la 11a (el formulario no los limita)', function () {
    // Sin W-2 y con una pérdida de negocio de 5.000: 9 = -5.000 y 11a = -5.000 - 0.
    $d = ingresos1040_declaracion('-5000', '0');
    (new Ingresos1040)->calcular(ingresos1040_entradas(), $d);

    expect(ingresos1040_valor($d, '9'))->toBe(-500000)
        ->and(ingresos1040_valor($d, '11a'))->toBe(-500000)
        ->and(ingresos1040_valor($d, '11b'))->toBe(-500000);

    // Todo en cero.
    $cero = ingresos1040_declaracion('0', '0');
    (new Ingresos1040)->calcular(ingresos1040_entradas(), $cero);

    expect(ingresos1040_valor($cero, '9'))->toBe(0)
        ->and(ingresos1040_valor($cero, '11a'))->toBe(0);
});

it('la 11a resta los ajustes aunque dejen el ingreso por debajo de cero', function () {
    // 9 = 1.000,00; 10 = 1.500,00 -> 11a = -500,00.
    $d = ingresos1040_declaracion('1000', '1500');
    (new Ingresos1040)->calcular(ingresos1040_entradas(), $d);

    expect(ingresos1040_valor($d, '11a'))->toBe(-50000);
});

it('suma a la 1z las líneas que otra calculadora ya puso, como la 1e del Form 2441', function () {
    $d = ingresos1040_declaracion('0', '0');
    $d->poner(Linea::calculada('1040', '1e', Dinero::deDolares('1200'), Traza::vacia()));
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('40000', 'w2:1')]), $d);

    // 1z = 1a (40.000) + 1e (1.200) = 41.200 (1040, p. 1: "Add lines 1a through 1h").
    expect(ingresos1040_valor($d, '1z'))->toBe(4120000)
        ->and(ingresos1040_valor($d, '9'))->toBe(4120000)
        ->and($d->linea('1040', '1e')->valor->centavos())->toBe(120000);
});

it('no toca la 1e: si la calculadora del 2441 no corrió, no existe y la 1z solo suma la 1a', function () {
    $d = ingresos1040_declaracion('0', '0');
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('40000', 'w2:1')]), $d);

    expect($d->linea('1040', '1e'))->toBeNull()
        ->and(ingresos1040_valor($d, '1z'))->toBe(4000000);
});

it('si la 1e quedó en falta_dato, la 1z y la 9 también', function () {
    $d = ingresos1040_declaracion('0', '0');
    $d->poner(Linea::faltaDato('1040', '1e', 'Falta algo del 2441'));
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('40000', 'w2:1')]), $d);

    expect($d->linea('1040', '1z')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '9')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '11a')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '11b')->estado)->toBe(EstadoLinea::FaltaDato);
});

it('la línea 9 sale no_soportada si hay un SSA-1099 y dice cuál documento', function () {
    $d = ingresos1040_declaracion('0', '0');
    $datos = [
        'w2:1' => ingresos1040_dato('30000', 'w2:1'),
        'ssa-1099:5' => ingresos1040_dato('12000', 'ssa-1099:5'),
    ];
    (new Ingresos1040)->calcular(ingresos1040_entradas($datos), $d);

    $nueve = $d->linea('1040', '9');

    expect($nueve->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($nueve->valor)->toBeNull()
        ->and($nueve->motivo)->toContain('ssa-1099')
        ->and($d->linea('1040', '11a')->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($d->linea('1040', '11b')->estado)->toBe(EstadoLinea::NoSoportada)
        ->and(ingresos1040_valor($d, '1z'))->toBe(3000000)
        ->and($d->estadoGeneral())->toBe('parcial');
});

it('cada documento fuera del piloto que cambia el ingreso total vuelve no_soportada la línea 9', function (string $documento) {
    $d = ingresos1040_declaracion('0', '0');
    (new Ingresos1040)->calcular(ingresos1040_entradas([$documento.':1' => ingresos1040_dato('100', $documento.':1')]), $d);

    expect($d->linea('1040', '9')->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($d->linea('1040', '9')->motivo)->toContain($documento);
})->with(['1099-int', '1099-div', '1099-r', 'ssa-1099', '1099-b', '1099-da', '1099-g', 'k-1']);

it('un 1098-E no afecta el ingreso total (es un ajuste del Schedule 1)', function () {
    $d = ingresos1040_declaracion('0', '0');
    (new Ingresos1040)->calcular(ingresos1040_entradas(['1098-e:1' => ingresos1040_dato('800', '1098-e:1')]), $d);

    expect($d->linea('1040', '9')->estado)->toBe(EstadoLinea::Calculada);
});

it('un documento que solo se parece al nombre no cuenta como fuera del piloto', function () {
    $d = ingresos1040_declaracion('0', '0');
    (new Ingresos1040)->calcular(ingresos1040_entradas(['1099-int-falso:1' => ingresos1040_dato('1', 'x:1')]), $d);

    expect($d->linea('1040', '9')->estado)->toBe(EstadoLinea::Calculada);
});

it('sin la línea 10 del Schedule 1 la 8, la 9, la 11a y la 11b salen en falta_dato con el motivo', function () {
    $d = ingresos1040_declaracion(null, '0');
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('30000', 'w2:1')]), $d);

    expect($d->linea('1040', '8')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '8')->motivo)->toBe('Falta la línea 10 del Schedule 1.')
        ->and($d->linea('1040', '9')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '11a')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '11b')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '10')->estado)->toBe(EstadoLinea::Calculada)
        ->and($d->estadoGeneral())->toBe('incompleta');
});

it('sin la línea 26 del Schedule 1 la 10 y la 11a salen en falta_dato y nunca suponen cero', function () {
    $d = ingresos1040_declaracion('0', null);
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('30000', 'w2:1')]), $d);

    expect($d->linea('1040', '10')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '10')->motivo)->toBe('Falta la línea 26 del Schedule 1.')
        ->and($d->linea('1040', '11a')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '9')->estado)->toBe(EstadoLinea::Calculada);
});

it('una línea 10 del Schedule 1 en falta_dato o no_soportada se hereda con su estado', function () {
    $d = new Declaracion(2025, 'f6-prueba');
    $d->poner(Linea::faltaDato('sch-1', '10', 'Falta la línea 31 del Schedule C'));
    $d->poner(Linea::calculada('sch-1', '26', Dinero::deDolares('0'), Traza::vacia()));
    (new Ingresos1040)->calcular(ingresos1040_entradas(), $d);

    expect($d->linea('1040', '8')->estado)->toBe(EstadoLinea::FaltaDato);

    $e = new Declaracion(2025, 'f6-prueba');
    $e->poner(Linea::noSoportada('sch-1', '10', 'K-1'));
    $e->poner(Linea::calculada('sch-1', '26', Dinero::deDolares('0'), Traza::vacia()));
    (new Ingresos1040)->calcular(ingresos1040_entradas(), $e);

    expect($e->linea('1040', '8')->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($e->linea('1040', '9')->estado)->toBe(EstadoLinea::NoSoportada);
});

it('una línea de Schedule 1 que no aplica cuenta cero y la 8 tampoco aplica', function () {
    $d = new Declaracion(2025, 'f6-prueba');
    $d->poner(Linea::noAplica('sch-1', '10'));
    $d->poner(Linea::calculada('sch-1', '26', Dinero::deDolares('0'), Traza::vacia()));
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('1000', 'w2:1')]), $d);

    expect($d->linea('1040', '8')->estado)->toBe(EstadoLinea::NoAplica)
        ->and(ingresos1040_valor($d, '9'))->toBe(100000);
});

it('la línea 1c solo se marca no_soportada si hay señal de propinas no reportadas', function () {
    // Sin señal: no se escribe la 1c.
    $d = ingresos1040_declaracion();
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('30000', 'w2:1'), 'w2:8' => ingresos1040_dato('0', 'w2:8')]), $d);
    expect($d->linea('1040', '1c'))->toBeNull()->and(ingresos1040_valor($d, '1z'))->toBe(3000000);

    // Propinas asignadas en la casilla 8 del W-2 (instrucciones del 1040, p. 22, línea 1c).
    $a = ingresos1040_declaracion();
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('30000', 'w2:1'), 'w2:8' => ingresos1040_dato('500', 'w2:8')]), $a);
    expect($a->linea('1040', '1c')->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($a->linea('1040', '1z')->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($a->linea('1040', '9')->estado)->toBe(EstadoLinea::NoSoportada);

    // Respuesta D3.1 "no" (no le reportó todas las propinas a su empleador).
    $b = ingresos1040_declaracion();
    (new Ingresos1040)->calcular(ingresos1040_entradas(['w2:1' => ingresos1040_dato('30000', 'w2:1')], ['D3.1' => new RespuestaEntrada(false)]), $b);
    expect($b->linea('1040', '1c')->estado)->toBe(EstadoLinea::NoSoportada);

    $c = ingresos1040_declaracion();
    (new Ingresos1040)->calcular(ingresos1040_entradas([], ['D3.1' => new RespuestaEntrada('no')]), $c);
    expect($c->linea('1040', '1c')->estado)->toBe(EstadoLinea::NoSoportada);

    // Respuesta "sí" (sí reportó todo): sin 1c.
    $e = ingresos1040_declaracion();
    (new Ingresos1040)->calcular(ingresos1040_entradas([], ['D3.1' => new RespuestaEntrada(true)]), $e);
    expect($e->linea('1040', '1c'))->toBeNull();
});

it('un dato sin validar o con excepción marca la 1a y todas las líneas que dependen de ella', function () {
    $d = ingresos1040_declaracion('0', '0');
    $datos = ['w2:1' => new DatoEntrada(Dinero::deDolares('30000'), OrigenTraza::Leido, 'dato-17', validado: false)];
    (new Ingresos1040)->calcular(ingresos1040_entradas($datos), $d);

    foreach (['1a', '1z', '9', '11a', '11b'] as $linea) {
        expect($d->linea('1040', $linea)->usaSinValidar)->toBeTrue($linea)
            ->and($d->linea('1040', $linea)->datosSinValidar)->toBe(['dato-17']);
    }

    // Con excepción abierta cuenta igual aunque esté validado.
    $e = ingresos1040_declaracion('0', '0');
    $datos = ['w2:1' => new DatoEntrada(Dinero::deDolares('30000'), OrigenTraza::Corregido, 'dato-18', validado: true, conExcepcion: true)];
    (new Ingresos1040)->calcular(ingresos1040_entradas($datos), $e);

    expect($e->linea('1040', '9')->datosSinValidar)->toBe(['dato-18']);
});

it('hereda los datos sin validar de las líneas del Schedule 1 sin repetirlos', function () {
    $d = new Declaracion(2025, 'f6-prueba');
    $d->poner(Linea::calculada('sch-1', '10', Dinero::deDolares('100'), Traza::vacia(), ['dato-3']));
    $d->poner(Linea::calculada('sch-1', '26', Dinero::deDolares('50'), Traza::vacia(), ['dato-3', 'dato-4']));
    (new Ingresos1040)->calcular(ingresos1040_entradas(), $d);

    expect($d->linea('1040', '8')->datosSinValidar)->toBe(['dato-3'])
        ->and($d->linea('1040', '11a')->datosSinValidar)->toBe(['dato-3', 'dato-4'])
        ->and($d->linea('1040', '1z')->usaSinValidar)->toBeFalse();
});

it('la traza lleva origen y referencias, nunca valores sensibles', function () {
    $d = ingresos1040_declaracion('20000', '1413');
    $datos = ['w2:1' => ingresos1040_dato('85000', 'w2:1', origen: OrigenTraza::Corregido)];
    (new Ingresos1040)->calcular(ingresos1040_entradas($datos), $d);

    $referencias = [];
    foreach ($d->lineas('1040') as $linea) {
        foreach ($linea->traza->pasos as $paso) {
            if ($paso->referencia !== null) {
                $referencias[] = $paso->referencia;
                expect($paso->referencia)->not->toMatch('/\d{3}-?\d{2}-?\d{4}/');
            }
        }
    }

    expect($d->linea('1040', '1a')->traza->pasos[0]->origen)->toBe(OrigenTraza::Corregido)
        ->and($d->linea('1040', '8')->traza->pasos[0]->origen)->toBe(OrigenTraza::Linea)
        ->and(array_unique($referencias))->each->toBeIn(['w2:1', '1040:1a', '1040:1z', '1040:8', '1040:9', '1040:10', '1040:11a', 'sch-1:10', 'sch-1:26']);
});
