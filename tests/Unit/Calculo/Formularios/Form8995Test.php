<?php

use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\Form8995;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Valores esperados armados a mano con el Form 8995 de 2025 (f8995--2025.pdf, p. 1) y sus
 * instrucciones (i8995--2025.pdf):
 * - Encabezado: el 8995 se usa si el ingreso gravable antes de la deducción es "at or below
 *   $197,300 ($394,600 if married filing jointly)".
 * - Línea 2: QBI; incluye restar la "deductible part of self-employment tax" (instrucciones, p. 2).
 * - Línea 4: 2 + 3, si es cero o menos, cero; línea 5: 4 × 20 %; línea 10: 5 + 9.
 * - Línea 11: 1040 línea 11a menos 12e y 13b (instrucciones, p. 4); 13 = 11 − 12 (mínimo cero);
 *   14 = 13 × 20 %; 15 = la menor entre 10 y 14 → 1040 línea 13a.
 * - Línea 4 (instrucciones, p. 4): con pérdida no hay deducción y la pérdida se arrastra (línea 16).
 */

beforeEach(function () {
    app()->setLocale('es');
    $this->seed(ParametrosFiscalesSeeder::class);
});

/**
 * Declaración con lo que el 8995 lee: Schedule C 31, Schedule 1 15, 1040 11a, 12e y 13b
 * (null = la línea no está; 'no_aplica' = no aplica).
 */
function f8995_declaracion(?string $schC31 = '50000', ?string $sch1_15 = '3532.50', string $agi = '46467.50', string $estandar = '15750', ?string $adicionales = 'no_aplica', int $anio = 2025): Declaracion
{
    $d = new Declaracion($anio, 'f6-prueba');
    $poner = function (string $formulario, string $linea, ?string $valor) use ($d): void {
        if ($valor === null) {
            return;
        }

        $d->poner($valor === 'no_aplica'
            ? Linea::noAplica($formulario, $linea)
            : Linea::calculada($formulario, $linea, Dinero::deDolares($valor), Traza::vacia()));
    };

    $poner('sch-c', '31', $schC31);
    $poner('sch-1', '15', $sch1_15);
    $poner('1040', '11a', $agi);
    $poner('1040', '12e', $estandar);
    $poner('1040', '13b', $adicionales);

    return $d;
}

/** @param array<string, DatoEntrada> $datos */
function f8995_calcular(Declaracion $d, ?EstadoCivilIrs $estado = EstadoCivilIrs::Single, array $datos = [], int $anio = 2025): Declaracion
{
    (new Form8995)->calcular(new EntradasDeclaracion($anio, $estado, datos: $datos), $d);

    return $d;
}

function f8995_valor(Declaracion $d, string $linea): int|float|null
{
    $valor = $d->linea('f8995', $linea)?->valor;

    return $valor instanceof Dinero ? $valor->centavos() / 100 : null;
}

function f8995_estado(Declaracion $d, string $linea): ?EstadoLinea
{
    return $d->linea('f8995', $linea)?->estado;
}

it('escribe el código de formulario f8995', function () {
    expect((new Form8995)->formulario())->toBe('f8995');
});

it('bajo el umbral, con el límite por ingreso más chico que el 20 % del QBI', function () {
    // 2 = 50.000 − 3.532,50 = 46.467,50; 5 = 10 = 9.293,50.
    // 11 = 46.467,50 − 15.750 − 0 = 30.717,50; 13 = 30.717,50; 14 = 6.143,50; 15 = 6.143,50.
    $d = f8995_calcular(f8995_declaracion());

    expect(f8995_valor($d, '2'))->toEqual(46467.5)
        ->and(f8995_estado($d, '3'))->toBe(EstadoLinea::NoAplica)
        ->and(f8995_valor($d, '4'))->toEqual(46467.5)
        ->and(f8995_valor($d, '5'))->toEqual(9293.5)
        ->and(f8995_valor($d, '10'))->toEqual(9293.5)
        ->and(f8995_valor($d, '11'))->toEqual(30717.5)
        ->and(f8995_valor($d, '12'))->toEqual(0)
        ->and(f8995_valor($d, '13'))->toEqual(30717.5)
        ->and(f8995_valor($d, '14'))->toEqual(6143.5)
        ->and(f8995_valor($d, '15'))->toEqual(6143.5)
        ->and(f8995_estado($d, '16'))->toBe(EstadoLinea::NoAplica);
});

it('bajo el umbral, con el 20 % del QBI más chico que el límite por ingreso', function () {
    // 2 = 20.000 − 1.413 = 18.587; 5 = 3.717,40. 11 = 80.000 − 15.750 = 64.250; 14 = 12.850; 15 = 3.717,40.
    $d = f8995_calcular(f8995_declaracion('20000', '1413', '80000'));

    expect(f8995_valor($d, '2'))->toEqual(18587)
        ->and(f8995_valor($d, '5'))->toEqual(3717.4)
        ->and(f8995_valor($d, '11'))->toEqual(64250)
        ->and(f8995_valor($d, '14'))->toEqual(12850)
        ->and(f8995_valor($d, '15'))->toEqual(3717.4);
});

it('la línea 11 resta la 13b del 1040 (Schedule 1-A)', function () {
    // 11 = 80.000 − 15.750 − 5.000 = 59.250.
    $d = f8995_calcular(f8995_declaracion('20000', '1413', '80000', adicionales: '5000'));

    expect(f8995_valor($d, '11'))->toEqual(59250);
});

it('justo en el umbral de soltero se calcula; un centavo arriba se usa el 8995-A', function () {
    // 213.050 − 15.750 = 197.300 (= umbral: "at or below").
    $enElUmbral = f8995_calcular(f8995_declaracion(agi: '213050'));
    $arriba = f8995_calcular(f8995_declaracion(agi: '213050.01'));

    expect(f8995_estado($enElUmbral, '15'))->toBe(EstadoLinea::Calculada)
        ->and(f8995_valor($enElUmbral, '11'))->toEqual(197300)
        ->and(f8995_estado($arriba, '15'))->toBe(EstadoLinea::NoSoportada)
        ->and($arriba->linea('f8995', '15')->motivo)->toContain('8995-A')
        ->and(f8995_estado($arriba, '2'))->toBe(EstadoLinea::NoSoportada);
});

it('en conjunta el umbral es USD 394.600', function () {
    // 331.500 − 31.500 = 300.000: con soltero se pasaría del umbral, en conjunta no.
    $d = f8995_calcular(f8995_declaracion(agi: '331500', estandar: '31500'), EstadoCivilIrs::Mfj);

    expect(f8995_estado($d, '15'))->toBe(EstadoLinea::Calculada)
        ->and(f8995_valor($d, '11'))->toEqual(300000);
});

it('con pérdida del negocio la deducción es cero y el arrastre lo registra el preparador', function () {
    $d = f8995_calcular(f8995_declaracion('-5000', '0', '30000'));

    expect(f8995_valor($d, '2'))->toEqual(-5000)
        ->and(f8995_valor($d, '4'))->toEqual(0)
        ->and(f8995_valor($d, '5'))->toEqual(0)
        ->and(f8995_valor($d, '15'))->toEqual(0)
        ->and(f8995_estado($d, '16'))->toBe(EstadoLinea::NoSoportada);
});

it('sin negocio en el Schedule C no aplica', function () {
    $d = f8995_calcular(f8995_declaracion('no_aplica'));

    expect(f8995_estado($d, '15'))->toBe(EstadoLinea::NoAplica)
        ->and(f8995_estado($d, '2'))->toBe(EstadoLinea::NoAplica);
});

it('sin la línea 31 del Schedule C es falta_dato', function () {
    $d = f8995_calcular(f8995_declaracion(null));

    expect(f8995_estado($d, '15'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('f8995', '15')->motivo)->toBe('Falta la línea 31 del Schedule C.');
});

it('con un 1099-DIV el formulario no se soporta', function () {
    $datos = ['1099-div:1a' => new DatoEntrada(Dinero::deDolares('300'), OrigenTraza::Leido, '1099-div#3:1a')];
    $d = f8995_calcular(f8995_declaracion(), datos: $datos);

    expect(f8995_estado($d, '15'))->toBe(EstadoLinea::NoSoportada)
        ->and($d->linea('f8995', '15')->motivo)->toContain('1099-div');
});

it('sin estado civil es falta_dato', function () {
    $d = f8995_calcular(f8995_declaracion(), null);

    expect(f8995_estado($d, '15'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('f8995', '15')->motivo)->toBe('Falta decidir el estado civil en Revisión.');
});

it('con la tasa pendiente es falta_dato con el nombre del parámetro', function () {
    ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'qbi')->where('clave', 'tasa')->update(['valor' => null]);
    ParametrosFiscales::invalidate();

    $d = f8995_calcular(f8995_declaracion());

    expect(f8995_estado($d, '15'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('f8995', '15')->motivo)->toBe('No se puede calcular: falta el parámetro qbi.tasa de 2025 (Catálogo → Parámetros).');
});

it('en 2026 sin parámetros cargados es falta_dato con el nombre del umbral', function () {
    $d = f8995_calcular(f8995_declaracion(anio: 2026), anio: 2026);

    expect(f8995_estado($d, '15'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('f8995', '15')->motivo)->toBe('No se puede calcular: falta el parámetro qbi.umbral_soltero de 2026 (Catálogo → Parámetros).');
});

it('un Schedule C sin validar marca el QBI y la deducción', function () {
    $d = f8995_declaracion();
    $d->poner(Linea::calculada('sch-c', '31', Dinero::deDolares('50000'), Traza::vacia(), ['1099-nec#4:1']));
    f8995_calcular($d);

    expect($d->linea('f8995', '2')->usaSinValidar)->toBeTrue()
        ->and($d->linea('f8995', '15')->datosSinValidar)->toBe(['1099-nec#4:1']);
});
