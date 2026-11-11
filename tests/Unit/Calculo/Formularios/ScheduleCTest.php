<?php

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\ScheduleC;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\TipoPaso;
use App\Support\ParametrosFiscales;
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Valores esperados, de los formularios e instrucciones del IRS de 2025:
 * - Schedule C (Form 1040) de 2025, p. 1: 3 "Subtract line 2 from line 1"; 5 "Subtract line 4 from
 *   line 3"; 7 "Add lines 5 and 6"; 28 "Add lines 8 through 27b"; 29 "Subtract line 28 from line 7";
 *   31 "Subtract line 30 from line 29". https://www.irs.gov/pub/irs-prior/f1040sc--2025.pdf
 * - Instrucciones del Schedule C de 2025 (i1040sc):
 *   p. 5, línea 1: "be sure line 1 includes amounts properly shown on your Forms 1099-NEC".
 *   p. 6, línea 9: "Multiply the number of business miles driven by 0.70. For example, 1,250
 *   business miles driven × 0.70 = $875.00".
 *   p. 10, línea 24b: "In most cases, you can deduct only 50% of your business meal expenses".
 *   p. 10, línea 30: área "by $5"; "cannot exceed 300 square feet".
 *   p. 12, Simplified Method Worksheet: 1 límite por el ingreso bruto (línea 29); 2 pies, no más de
 *   300; 3a $5; 4 = 2 × 3c; 5 "Enter the smaller of line 1 or line 4 ... If zero or less, enter -0-".
 *   https://www.irs.gov/pub/irs-prior/i1040sc--2025.pdf
 * - Parámetros de 2025 (ParametrosFiscalesSeeder): schedule_c.millaje_estandar 0.70,
 *   comidas_porcentaje_deducible 0.50, oficina_casa_tarifa_pie_cuadrado 5,
 *   oficina_casa_tope_pies_cuadrados 300.
 */

beforeEach(function () {
    // Los motivos se comparan en español; el idioma por defecto de la aplicación es inglés.
    app()->setLocale('es');
    $this->seed(ParametrosFiscalesSeeder::class);
});

function schc_dato(string $dolares, string $referencia, bool $validado = true): DatoEntrada
{
    return new DatoEntrada(Dinero::deDolares($dolares), OrigenTraza::Leido, $referencia, $validado);
}

function schc_doc(string $codigo, int $itemId, ?int $personaId): DocumentoEntrada
{
    return new DocumentoEntrada($codigo, $itemId, $personaId, []);
}

/** Registros: montos en dólares; las cantidades (millas, pies) también, como hace SumasRegistros. */
function schc_registros(array $sumas): array
{
    return array_map(fn (string $v): Dinero => Dinero::deDolares($v), $sumas);
}

function schc_entradas(array $datos = [], array $registros = [], array $documentos = [], int $anio = 2025): EntradasDeclaracion
{
    return new EntradasDeclaracion(
        $anio,
        EstadoCivilIrs::Single,
        personas: [new PersonaEntrada(1, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, 40, true, false)],
        datos: $datos,
        sumasRegistros: schc_registros($registros),
        documentos: $documentos,
    );
}

function schc_calcular(EntradasDeclaracion $e, int $anio = 2025): Declaracion
{
    $d = new Declaracion($anio, 'f6-prueba');
    (new ScheduleC)->calcular($e, $d);

    return $d;
}

function schc_valor(Declaracion $d, string $linea): ?int
{
    $valor = $d->linea('sch-c', $linea)?->valor;

    return $valor instanceof Dinero ? $valor->centavos() : null;
}

function schc_estado(Declaracion $d, string $linea): ?EstadoLinea
{
    return $d->linea('sch-c', $linea)?->estado;
}

it('escribe el código de formulario sch-c', function () {
    expect((new ScheduleC)->formulario())->toBe('sch-c');
});

it('sin 1099 ni registros todas las líneas no aplican', function () {
    $d = schc_calcular(schc_entradas(datos: ['w2:1' => schc_dato('40000.00', 'w2:1')]));

    foreach (ScheduleC::LINEAS as $linea) {
        expect(schc_estado($d, $linea))->toBe(EstadoLinea::NoAplica);
    }
    expect($d->linea('sch-c', '31')->motivo)->toBe('No hay 1099-NEC, 1099-K ni registros del negocio.');
});

it('la línea 1 suma 1099-NEC, 1099-K e ingresos sin formulario y baja por las devoluciones', function () {
    // 1 = 12.000,00 (1099-NEC c. 1) + 3.500,50 (1099-K c. 1a) + 1.200,00 (sin formulario) = 16.700,50.
    // 2 = 200,00; 3 = 16.500,50; 4 no aplica (sin inventario); 5 = 16.500,50; 6 no aplica; 7 = 16.500,50.
    $d = schc_calcular(schc_entradas(
        datos: ['1099-nec:1' => schc_dato('12000.00', '1099-nec:1'), '1099-k:1a' => schc_dato('3500.50', '1099-k:1a')],
        registros: ['1-sin-formulario' => '1200.00', '2' => '200.00'],
    ));

    expect(schc_valor($d, '1'))->toBe(1_670_050)
        ->and(schc_valor($d, '2'))->toBe(20_000)
        ->and(schc_valor($d, '3'))->toBe(1_650_050)
        ->and(schc_estado($d, '4'))->toBe(EstadoLinea::NoAplica)
        ->and(schc_valor($d, '5'))->toBe(1_650_050)
        ->and(schc_estado($d, '6'))->toBe(EstadoLinea::NoAplica)
        ->and(schc_valor($d, '7'))->toBe(1_650_050)
        // Sin gastos: 28 = 0; 29 = 7; sin oficina en casa: 30 no aplica; 31 = 29.
        ->and(schc_valor($d, '28'))->toBe(0)
        ->and(schc_valor($d, '29'))->toBe(1_650_050)
        ->and(schc_estado($d, '30'))->toBe(EstadoLinea::NoAplica)
        ->and(schc_valor($d, '31'))->toBe(1_650_050);

    $referencias = array_map(fn ($p) => $p->referencia, array_filter($d->linea('sch-c', '1')->traza->pasos, fn ($p) => $p->tipo === TipoPaso::Entrada));
    expect(array_values($referencias))->toBe(['1099-nec:1', '1099-k:1a', 'registros-ingresos-gastos:1-sin-formulario']);
});

it('los gastos salen de los registros por línea y las categorías sin datos no aplican', function () {
    // 8 = 500,00; 18 = 1.250,25; 22 = 300,00 → 28 = 2.050,25; 29 = 10.000,00 − 2.050,25 = 7.949,75.
    $d = schc_calcular(schc_entradas(
        datos: ['1099-nec:1' => schc_dato('10000.00', '1099-nec:1')],
        registros: ['8' => '500.00', '18' => '1250.25', '22' => '300.00'],
    ));

    expect(schc_valor($d, '8'))->toBe(50_000)
        ->and(schc_valor($d, '18'))->toBe(125_025)
        ->and(schc_valor($d, '22'))->toBe(30_000)
        ->and(schc_estado($d, '10'))->toBe(EstadoLinea::NoAplica)
        ->and(schc_estado($d, '27b'))->toBe(EstadoLinea::NoAplica)
        ->and(schc_valor($d, '28'))->toBe(205_025)
        ->and(schc_valor($d, '29'))->toBe(794_975)
        ->and(schc_valor($d, '31'))->toBe(794_975);
});

it('la línea 9 multiplica las millas por la tarifa estándar del año', function () {
    // i1040sc de 2025, p. 6: "1,250 business miles driven × 0.70 = $875.00".
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('5000.00', '1099-nec:1')], registros: ['millas' => '1250']));

    expect(schc_valor($d, '9'))->toBe(87_500)
        ->and(schc_valor($d, '28'))->toBe(87_500)
        ->and(schc_valor($d, '31'))->toBe(412_500);

    $parametros = array_filter($d->linea('sch-c', '9')->traza->pasos, fn ($p) => $p->tipo === TipoPaso::Parametro);
    expect(array_values($parametros)[0]->referencia)->toBe('schedule_c.millaje_estandar');
});

it('con millas y gastos reales del vehículo la línea 9 queda para que elija el preparador', function () {
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('5000.00', '1099-nec:1')], registros: ['millas' => '1250', '9' => '900.00']));

    expect(schc_estado($d, '9'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-c', '9')->motivo)->toContain('el preparador elige la tarifa estándar o los gastos reales')
        ->and(schc_estado($d, '28'))->toBe(EstadoLinea::FaltaDato)
        ->and(schc_estado($d, '31'))->toBe(EstadoLinea::FaltaDato);
});

it('sin millas la línea 9 toma los gastos reales de los registros', function () {
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('5000.00', '1099-nec:1')], registros: ['9' => '900.00']));

    expect(schc_valor($d, '9'))->toBe(90_000);
});

it('la línea 24b deduce las comidas al porcentaje del año', function () {
    // i1040sc de 2025, p. 10: 50 % de 800,00 = 400,00.
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('5000.00', '1099-nec:1')], registros: ['24b' => '800.00']));

    expect(schc_valor($d, '24b'))->toBe(40_000)
        ->and(schc_valor($d, '28'))->toBe(40_000);
});

it('la depreciación con datos sale no soportada y arrastra el total', function () {
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('5000.00', '1099-nec:1')], registros: ['13' => '2000.00']));

    expect(schc_estado($d, '13'))->toBe(EstadoLinea::NoSoportada)
        ->and($d->linea('sch-c', '13')->motivo)->toContain('Form 4562')
        ->and(schc_estado($d, '28'))->toBe(EstadoLinea::NoSoportada)
        ->and(schc_estado($d, '31'))->toBe(EstadoLinea::NoSoportada);
});

it('la oficina en casa usa el método simplificado con el tope de pies cuadrados', function () {
    // Hoja del método simplificado (i1040sc de 2025, p. 12): 350 pies → línea 2 = 300; 4 = 300 × 5 = 1.500,00;
    // línea 1 = línea 29 = 20.000,00; 5 = la menor = 1.500,00. 31 = 20.000,00 − 1.500,00 = 18.500,00.
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('20000.00', '1099-nec:1')], registros: ['pies-oficina' => '350', 'pies-casa' => '1800']));

    expect(schc_valor($d, '30'))->toBe(150_000)
        ->and(schc_valor($d, '31'))->toBe(1_850_000);
});

it('la oficina en casa no pasa de la ganancia de la línea 29', function () {
    // 29 = 1.000,00; 250 pies × 5 = 1.250,00; línea 5 = la menor = 1.000,00; 31 = 0.
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('1000.00', '1099-nec:1')], registros: ['pies-oficina' => '250']));

    expect(schc_valor($d, '30'))->toBe(100_000)
        ->and(schc_valor($d, '31'))->toBe(0);

    $operaciones = array_map(fn ($p) => $p->texto, $d->linea('sch-c', '30')->traza->pasos);
    expect($operaciones)->toContain('Faltan los pies cuadrados de toda la casa, que el formulario pide junto a la línea 30.');
});

it('con pérdida la línea 31 sale negativa, la oficina en casa en cero y la traza avisa de la 32', function () {
    // 29 = 1.000,00 − 3.000,00 = −2.000,00; hoja simplificada línea 5: cero o menos → 0; 31 = −2.000,00.
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('1000.00', '1099-nec:1')], registros: ['8' => '3000.00', 'pies-oficina' => '100']));

    expect(schc_valor($d, '29'))->toBe(-200_000)
        ->and(schc_valor($d, '30'))->toBe(0)
        ->and(schc_valor($d, '31'))->toBe(-200_000);

    $textos = array_map(fn ($p) => $p->texto, $d->linea('sch-c', '31')->traza->pasos);
    expect(implode(' ', $textos))->toContain('el preparador marca la línea 32a o 32b');
});

it('con 1099 de trabajo independiente de dos personas todo el Schedule C sale no soportado', function () {
    $e = new EntradasDeclaracion(
        2025,
        EstadoCivilIrs::Mfj,
        personas: [
            new PersonaEntrada(1, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, 40, true, false),
            new PersonaEntrada(2, RolPersona::Conyuge, ClasificacionPersona::NoDependiente, 38, true, false),
        ],
        datos: ['1099-nec:1' => schc_dato('9000.00', '1099-nec:1')],
        documentos: ['1099-nec' => [schc_doc('1099-nec', 10, 1), schc_doc('1099-nec', 11, 2)]],
    );
    $d = schc_calcular($e);

    foreach (ScheduleC::LINEAS as $linea) {
        expect(schc_estado($d, $linea))->toBe(EstadoLinea::NoSoportada);
    }
    expect($d->linea('sch-c', '1')->motivo)->toContain('son dos negocios');
});

it('un 1099 sin validar marca las líneas que dependen de él', function () {
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('7000.00', '1099-nec:1', validado: false)], registros: ['8' => '100.00']));

    expect($d->linea('sch-c', '1')->usaSinValidar)->toBeTrue()
        ->and($d->linea('sch-c', '1')->datosSinValidar)->toBe(['1099-nec:1'])
        ->and($d->linea('sch-c', '31')->usaSinValidar)->toBeTrue()
        ->and($d->linea('sch-c', '8')->usaSinValidar)->toBeFalse();
});

it('en 2026 con el millaje pendiente la línea 9 sale falta_dato con el nombre del parámetro', function () {
    foreach (ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'schedule_c')->get() as $p) {
        ParametroFiscal::create([
            'tax_year' => 2026,
            'categoria' => $p->categoria,
            'clave' => $p->clave,
            'valor' => $p->clave === 'millaje_estandar' ? null : $p->valor,
        ]);
    }
    ParametrosFiscales::invalidate();

    // En 2026 la compensación de no empleado del 1099-NEC está en la casilla 1a (catálogo de 2026).
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1a' => schc_dato('5000.00', '1099-nec:1a')], registros: ['millas' => '1000'], anio: 2026), 2026);

    expect(schc_valor($d, '1'))->toBe(500_000)
        ->and(schc_estado($d, '9'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-c', '9')->motivo)->toBe('No se puede calcular: falta el parámetro schedule_c.millaje_estandar de 2026 (Catálogo → Parámetros).');
});

it('un 1099-MISC con casillas 5 o 6 deja la línea 1 para el preparador', function () {
    $d = schc_calcular(schc_entradas(datos: ['1099-nec:1' => schc_dato('5000.00', '1099-nec:1'), '1099-misc:6' => schc_dato('800.00', '1099-misc:6')]));

    expect(schc_estado($d, '1'))->toBe(EstadoLinea::NoSoportada)
        ->and(schc_estado($d, '31'))->toBe(EstadoLinea::NoSoportada);
});

it('con ventas para reventa en el 1099-NEC la línea 4 sale no soportada', function () {
    $d = schc_calcular(schc_entradas(datos: [
        '1099-nec:1' => schc_dato('5000.00', '1099-nec:1'),
        '1099-nec:2' => new DatoEntrada('si', OrigenTraza::Leido, '1099-nec:2'),
    ]));

    expect(schc_estado($d, '4'))->toBe(EstadoLinea::NoSoportada)
        ->and(schc_estado($d, '5'))->toBe(EstadoLinea::NoSoportada);
});

it('con registros de gastos pero sin ningún ingreso la línea 1 sale falta_dato', function () {
    $d = schc_calcular(schc_entradas(registros: ['18' => '300.00']));

    expect(schc_estado($d, '1'))->toBe(EstadoLinea::FaltaDato)
        ->and(schc_valor($d, '18'))->toBe(30_000)
        ->and(schc_estado($d, '31'))->toBe(EstadoLinea::FaltaDato);
});

it('la traza lleva referencias y nunca valores sensibles', function () {
    $d = schc_calcular(schc_entradas(
        datos: ['1099-nec:1' => schc_dato('20000.00', '1099-nec:1'), '1099-k:1a' => schc_dato('1000.00', '1099-k:1a')],
        registros: ['1-sin-formulario' => '500.00', '8' => '100.00', 'millas' => '100', '24b' => '60.00', 'pies-oficina' => '120'],
    ));

    foreach ($d->lineas('sch-c') as $linea) {
        foreach ($linea->traza->pasos as $paso) {
            if ($paso->referencia !== null) {
                expect($paso->referencia)->toMatch('/^(1099-nec|1099-k|registros-ingresos-gastos|sch-c|schedule_c)[:.]/');
            }
        }
    }
});
