<?php

use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\Schedule1;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\TipoPaso;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Valores esperados, de los formularios del IRS de 2025:
 * - Schedule 1 (Form 1040) de 2025, p. 1: línea 3 "Business income or (loss). Attach Schedule C";
 *   línea 9 "Total other income. Add lines 8a through 8z"; línea 10 "Combine lines 1 through 7 and 9".
 *   P. 2: línea 11 "Educator expenses"; línea 15 "Deductible part of self-employment tax";
 *   línea 26 "Add lines 11 through 23 and 25".
 *   https://www.irs.gov/pub/irs-prior/f1040s1--2025.pdf
 * - Instrucciones del Form 1040 de 2025, p. 93, "Educator Expenses": "you can deduct on line 11 up to
 *   $300 of qualified expenses you paid in 2025. If you and your spouse are filing jointly and both of
 *   you were eligible educators, the maximum deduction is $600. However, neither spouse can deduct
 *   more than $300." https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf
 * - Formulario 1099-MISC: casilla 3 "Other income", casilla 8 "Substitute payments in lieu of dividends
 *   or interest" (ambas a la línea 8z del Schedule 1 según el catálogo de 6.0).
 */

beforeEach(function () {
    $this->seed(ParametrosFiscalesSeeder::class);
});

function sch1_dato(string $dolares, string $referencia, bool $validado = true): DatoEntrada
{
    return new DatoEntrada(Dinero::deDolares($dolares), OrigenTraza::Leido, $referencia, $validado);
}

/** @param array<string, DatoEntrada> $datos */
function sch1_entradas(array $datos = [], ?EstadoCivilIrs $estadoCivil = EstadoCivilIrs::Single): EntradasDeclaracion
{
    return new EntradasDeclaracion(2025, $estadoCivil, datos: $datos);
}

/** Declaración con la línea 31 del Schedule C y la 13 del Schedule SE (null = no está). */
function sch1_declaracion(?string $schC31 = '0', ?string $schSe13 = '0'): Declaracion
{
    $d = new Declaracion(2025, 'f6-prueba');

    if ($schC31 !== null) {
        $d->poner(Linea::calculada('sch-c', '31', Dinero::deDolares($schC31), Traza::vacia()));
    }
    if ($schSe13 !== null) {
        $d->poner(Linea::calculada('sch-se', '13', Dinero::deDolares($schSe13), Traza::vacia()));
    }

    return $d;
}

function sch1_valor(Declaracion $d, string $linea): ?int
{
    return $d->linea('sch-1', $linea)?->valor?->centavos();
}

it('escribe el código de formulario sch-1', function () {
    expect((new Schedule1)->formulario())->toBe('sch-1');
});

it('la línea 3 copia la 31 del Schedule C y la 10 combina la 3 con la 9', function () {
    // Ganancia del Schedule C 18.250,75 + otros ingresos 1099-MISC (casilla 3: 1.500,00 + casilla 8: 200,50 = 1.700,50)
    // 3 = 18.250,75; 8z = 1.700,50; 9 = 8z = 1.700,50; 10 = 18.250,75 + 1.700,50 = 19.951,25.
    $d = sch1_declaracion('18250.75', '0');
    (new Schedule1)->calcular(sch1_entradas(['1099-misc:3' => sch1_dato('1500', 'd1'), '1099-misc:8' => sch1_dato('200.50', 'd2')]), $d);

    expect(sch1_valor($d, '3'))->toBe(1825075)
        ->and(sch1_valor($d, '8z'))->toBe(170050)
        ->and(sch1_valor($d, '9'))->toBe(170050)
        ->and(sch1_valor($d, '10'))->toBe(1995125)
        ->and($d->estadoGeneral())->toBe('completa');
});

it('la línea 8z suma solo las casillas que existen y sin 1099-MISC no aplica', function () {
    $solo3 = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['1099-misc:3' => sch1_dato('900', 'd1')]), $solo3);
    expect(sch1_valor($solo3, '8z'))->toBe(90000);

    $ninguna = sch1_declaracion('1000');
    (new Schedule1)->calcular(sch1_entradas(), $ninguna);
    expect($ninguna->linea('sch-1', '8z')->estado)->toBe(EstadoLinea::NoAplica)
        ->and(sch1_valor($ninguna, '9'))->toBe(0)
        ->and(sch1_valor($ninguna, '10'))->toBe(100000);
});

it('mantiene la pérdida del Schedule C en la 3 y en la 10', function () {
    $d = sch1_declaracion('-4000', '0');
    (new Schedule1)->calcular(sch1_entradas(), $d);

    expect(sch1_valor($d, '3'))->toBe(-400000)
        ->and(sch1_valor($d, '10'))->toBe(-400000);
});

it('sin la línea 31 del Schedule C la 3 y la 10 salen en falta_dato y lo demás sigue', function () {
    $d = sch1_declaracion(null, '500');
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('200', 'd1')]), $d);

    expect($d->linea('sch-1', '3')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1', '3')->motivo)->toBe('Falta la línea 31 del Schedule C.')
        ->and($d->linea('sch-1', '10')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1', '10')->valor)->toBeNull()
        ->and(sch1_valor($d, '11'))->toBe(20000)
        ->and(sch1_valor($d, '26'))->toBe(70000)
        ->and($d->estadoGeneral())->toBe('incompleta');
});

it('si el Schedule C no aplica, la 3 no aplica y la 10 cuenta cero', function () {
    $d = new Declaracion(2025, 'f6-prueba');
    $d->poner(Linea::noAplica('sch-c', '31'));
    $d->poner(Linea::noAplica('sch-se', '13'));
    (new Schedule1)->calcular(sch1_entradas(), $d);

    expect($d->linea('sch-1', '3')->estado)->toBe(EstadoLinea::NoAplica)
        ->and($d->linea('sch-1', '15')->estado)->toBe(EstadoLinea::NoAplica)
        ->and(sch1_valor($d, '10'))->toBe(0)
        ->and(sch1_valor($d, '26'))->toBe(0);
});

it('hereda falta_dato y no_soportada del Schedule C', function () {
    $d = new Declaracion(2025, 'f6-prueba');
    $d->poner(Linea::faltaDato('sch-c', '31', 'Falta algo'));
    $d->poner(Linea::noSoportada('sch-se', '13', 'No se soporta'));
    (new Schedule1)->calcular(sch1_entradas(), $d);

    expect($d->linea('sch-1', '3')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1', '15')->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($d->linea('sch-1', '26')->estado)->toBe(EstadoLinea::NoSoportada);
});

it('gastos de educador: por debajo del tope se deduce todo', function () {
    $d = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('250', 'rec-1')]), $d);

    expect(sch1_valor($d, '11'))->toBe(25000);
});

it('gastos de educador: en el tope exacto y por encima se limita a USD 300', function (string $gastado) {
    // Instrucciones del 1040 de 2025, p. 93: "up to $300 of qualified expenses".
    $d = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato($gastado, 'rec-1')]), $d);

    expect(sch1_valor($d, '11'))->toBe(30000);
})->with(['300', '342.18', '1200']);

it('gastos de educador: la traza dice el total, el tope con su clave y el menor de los dos', function () {
    $d = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('450', 'rec-1')]), $d);

    $pasos = $d->linea('sch-1', '11')->traza->pasos;

    expect($pasos[0]->tipo)->toBe(TipoPaso::Entrada)
        ->and($pasos[0]->referencia)->toBe('rec-1')
        ->and($pasos[1]->tipo)->toBe(TipoPaso::Parametro)
        ->and($pasos[1]->referencia)->toBe('schedule_1.tope_gastos_educador')
        ->and($pasos[1]->valor->centavos())->toBe(30000)
        ->and(end($pasos)->tipo)->toBe(TipoPaso::Resultado);
});

it('gastos de educador: con declaración conjunta por encima del tope deja la nota para el preparador', function () {
    // Decisión pendiente: con MFJ y los dos cónyuges educadores el tope sería de USD 300 por cónyuge (USD 600).
    // Primera versión: se aplica una sola vez y se avisa.
    $mfj = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('450', 'rec-1')], EstadoCivilIrs::Mfj), $mfj);

    $textos = array_map(fn ($p) => $p->texto, $mfj->linea('sch-1', '11')->traza->pasos);

    expect(sch1_valor($mfj, '11'))->toBe(30000)
        ->and(implode(' | ', $textos))->toContain('tope es doble');

    // Sin pasarse del tope, con MFJ, no hay nota; y soltero, tampoco.
    $mfjDebajo = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('250', 'rec-1')], EstadoCivilIrs::Mfj), $mfjDebajo);
    $soltero = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('450', 'rec-1')]), $soltero);

    foreach ([$mfjDebajo, $soltero] as $d) {
        expect(implode(' | ', array_map(fn ($p) => $p->texto, $d->linea('sch-1', '11')->traza->pasos)))->not->toContain('tope es doble');
    }
});

it('gastos de educador: sin estado civil igual se calcula con el tope de una persona', function () {
    $d = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('450', 'rec-1')], null), $d);

    expect(sch1_valor($d, '11'))->toBe(30000);
});

it('gastos de educador: sin recibos no aplica y con un total sin monto es falta_dato', function () {
    $sin = sch1_declaracion('0', '700');
    (new Schedule1)->calcular(sch1_entradas(), $sin);
    expect($sin->linea('sch-1', '11')->estado)->toBe(EstadoLinea::NoAplica)
        ->and(sch1_valor($sin, '26'))->toBe(70000);

    $vacio = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => new DatoEntrada(null, OrigenTraza::Leido, 'rec-1')]), $vacio);
    expect($vacio->linea('sch-1', '11')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($vacio->linea('sch-1', '26')->estado)->toBe(EstadoLinea::FaltaDato);
});

it('gastos de educador: un total negativo no deja una deducción negativa', function () {
    $d = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('-50', 'rec-1')]), $d);

    expect(sch1_valor($d, '11'))->toBe(0);
});

it('gastos de educador: con el parámetro del tope pendiente la línea es falta_dato con el motivo en palabras', function () {
    ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'schedule_1')->where('clave', 'tope_gastos_educador')->update(['valor' => null]);
    ParametrosFiscales::invalidate();

    $d = sch1_declaracion('0', '500');
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('450', 'rec-1')]), $d);

    expect($d->linea('sch-1', '11')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1', '11')->motivo)->toBe('No se puede calcular: falta el parámetro schedule_1.tope_gastos_educador de 2025 (Catálogo → Parámetros).')
        ->and($d->linea('sch-1', '26')->estado)->toBe(EstadoLinea::FaltaDato);
});

it('la 15 copia la línea 13 del Schedule SE y la 26 suma la 11 y la 15', function () {
    // 11 = 300,00 (tope); 15 = 1.288,27 (mitad del impuesto de autoempleo) -> 26 = 1.588,27.
    $d = sch1_declaracion('9000', '1288.27');
    (new Schedule1)->calcular(sch1_entradas(['recibos-utiles-educador:total' => sch1_dato('340', 'rec-1')]), $d);

    expect(sch1_valor($d, '15'))->toBe(128827)
        ->and(sch1_valor($d, '11'))->toBe(30000)
        ->and(sch1_valor($d, '26'))->toBe(158827);
});

it('sin la línea 13 del Schedule SE la 15 y la 26 salen en falta_dato', function () {
    $d = sch1_declaracion('9000', null);
    (new Schedule1)->calcular(sch1_entradas(), $d);

    expect($d->linea('sch-1', '15')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1', '15')->motivo)->toBe('Falta la línea 13 del Schedule SE.')
        ->and($d->linea('sch-1', '26')->estado)->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-1', '10')->estado)->toBe(EstadoLinea::Calculada);
});

it('la 10 sale no_soportada con un 1099-G o un K-1 y la 26 con un 1098-E', function (string $documento, string $linea) {
    $d = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas([$documento.':1' => sch1_dato('100', $documento.':1')]), $d);

    expect($d->linea('sch-1', $linea)->estado)->toBe(EstadoLinea::NoSoportada)
        ->and($d->linea('sch-1', $linea)->motivo)->toContain($documento)
        ->and($d->estadoGeneral())->toBe('parcial');
})->with([
    ['1099-g', '10'],
    ['k-1', '10'],
    ['1098-e', '26'],
]);

it('un SSA-1099 no afecta el Schedule 1 (va a la línea 6b del 1040)', function () {
    $d = sch1_declaracion();
    (new Schedule1)->calcular(sch1_entradas(['ssa-1099:5' => sch1_dato('9000', 'ssa')]), $d);

    expect($d->linea('sch-1', '10')->estado)->toBe(EstadoLinea::Calculada)
        ->and($d->linea('sch-1', '26')->estado)->toBe(EstadoLinea::Calculada);
});

it('un dato sin validar marca la línea que lo usa y las que dependen de ella', function () {
    $d = sch1_declaracion('0', '0');
    $datos = [
        '1099-misc:3' => sch1_dato('500', 'dato-8', validado: false),
        'recibos-utiles-educador:total' => new DatoEntrada(Dinero::deDolares('200'), OrigenTraza::Corregido, 'dato-9', validado: true, conExcepcion: true),
    ];
    (new Schedule1)->calcular(sch1_entradas($datos), $d);

    expect($d->linea('sch-1', '8z')->datosSinValidar)->toBe(['dato-8'])
        ->and($d->linea('sch-1', '9')->datosSinValidar)->toBe(['dato-8'])
        ->and($d->linea('sch-1', '10')->datosSinValidar)->toBe(['dato-8'])
        ->and($d->linea('sch-1', '10')->usaSinValidar)->toBeTrue()
        ->and($d->linea('sch-1', '11')->datosSinValidar)->toBe(['dato-9'])
        ->and($d->linea('sch-1', '26')->datosSinValidar)->toBe(['dato-9'])
        ->and($d->linea('sch-1', '3')->usaSinValidar)->toBeFalse();
});

it('la traza lleva origen y referencias, nunca valores sensibles', function () {
    $d = sch1_declaracion('1000', '50');
    $datos = ['1099-misc:3' => sch1_dato('500', 'dato-8'), 'recibos-utiles-educador:total' => sch1_dato('200', 'rec-1')];
    (new Schedule1)->calcular(sch1_entradas($datos), $d);

    $permitidas = ['dato-8', 'rec-1', 'schedule_1.tope_gastos_educador', 'sch-c:31', 'sch-se:13', 'sch-1:3', 'sch-1:8z', 'sch-1:9', 'sch-1:11', 'sch-1:15', '1099-misc:3'];

    foreach ($d->lineas('sch-1') as $linea) {
        foreach ($linea->traza->pasos as $paso) {
            if ($paso->referencia !== null) {
                expect($paso->referencia)->toBeIn($permitidas)->not->toMatch('/\d{3}-?\d{2}-?\d{4}/');
            }
        }
    }

    expect($d->linea('sch-1', '8z')->traza->pasos[0]->origen)->toBe(OrigenTraza::Leido)
        ->and($d->linea('sch-1', '10')->traza->pasos[0]->origen)->toBe(OrigenTraza::Linea);
});
