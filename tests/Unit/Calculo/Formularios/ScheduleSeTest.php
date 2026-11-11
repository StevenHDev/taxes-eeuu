<?php

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\Schedule1;
use App\Services\Calculo\Formularios\ScheduleC;
use App\Services\Calculo\Formularios\ScheduleSe;
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
use App\Support\ParametrosFiscales;
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Valores esperados, del Schedule SE (Form 1040) de 2025, p. 1
 * (https://www.irs.gov/pub/irs-prior/f1040sse--2025.pdf):
 * - 2 "Net profit or (loss) from Schedule C, line 31"; 3 "Combine lines 1a, 1b, and 2".
 * - 4a "If line 3 is more than zero, multiply line 3 by 92.35% (0.9235). Otherwise, enter amount from line 3".
 * - 4c "If less than $400, stop; you don't owe self-employment tax".
 * - 7 "$176,100"; 8a "total of boxes 3 and 7 on Form(s) W-2"; 9 "Subtract line 8d from line 7. If zero or
 *   less, enter -0- here and on line 10"; 10 "Multiply the smaller of line 6 or line 9 by 12.4% (0.124)";
 *   11 "Multiply line 6 by 2.9% (0.029)"; 12 "Add lines 10 and 11"; 13 "Multiply line 12 by 50% (0.50)".
 * - Instrucciones del Schedule SE de 2025, p. 2, "Joint Returns": "If both spouses have self-employment
 *   income, each must file a separate Schedule SE" (los W-2 del otro cónyuge no van en la 8a).
 *   https://www.irs.gov/pub/irs-prior/i1040sse--2025.pdf
 * Los centavos se redondean al centavo (mitad hacia afuera del cero), como hace `Dinero::por()`.
 */

beforeEach(function () {
    // Los motivos se comparan en español; el idioma por defecto de la aplicación es inglés.
    app()->setLocale('es');
    $this->seed(ParametrosFiscalesSeeder::class);
});

function schse_w2(int $itemId, ?int $personaId, string $casilla3, ?string $casilla7 = null, bool $validado = true): DocumentoEntrada
{
    $casillas = ['3' => new DatoEntrada(Dinero::deDolares($casilla3), OrigenTraza::Leido, "w2#{$itemId}:3", $validado)];

    if ($casilla7 !== null) {
        $casillas['7'] = new DatoEntrada(Dinero::deDolares($casilla7), OrigenTraza::Leido, "w2#{$itemId}:7", $validado);
    }

    return new DocumentoEntrada('w2', $itemId, $personaId, $casillas);
}

/**
 * Contribuyente (1) dueño del 1099-NEC; con `$conyuge`, también el cónyuge (2).
 *
 * @param  list<DocumentoEntrada>  $w2s
 * @param  list<DocumentoEntrada>  $nec
 */
function schse_entradas(array $w2s = [], bool $conyuge = false, ?array $nec = null, array $datos = []): EntradasDeclaracion
{
    $personas = [new PersonaEntrada(1, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, 40, true, false)];

    if ($conyuge) {
        $personas[] = new PersonaEntrada(2, RolPersona::Conyuge, ClasificacionPersona::NoDependiente, 38, true, false);
    }

    return new EntradasDeclaracion(
        2025,
        $conyuge ? EstadoCivilIrs::Mfj : EstadoCivilIrs::Single,
        personas: $personas,
        datos: ['1099-nec:1' => new DatoEntrada(Dinero::deDolares('1.00'), OrigenTraza::Leido, '1099-nec:1'), ...$datos],
        documentos: ['1099-nec' => $nec ?? [new DocumentoEntrada('1099-nec', 50, 1, [])], 'w2' => $w2s],
    );
}

/** Declaración con la línea 31 del Schedule C (null = no está). */
function schse_calcular(EntradasDeclaracion $e, ?string $c31, array $sinValidar = []): Declaracion
{
    $d = new Declaracion(2025, 'f6-prueba');

    if ($c31 !== null) {
        $d->poner(Linea::calculada('sch-c', '31', Dinero::deDolares($c31), Traza::vacia(), $sinValidar));
    }

    (new ScheduleSe)->calcular($e, $d);

    return $d;
}

function schse_valor(Declaracion $d, string $linea): ?int
{
    $valor = $d->linea('sch-se', $linea)?->valor;

    return $valor instanceof Dinero ? $valor->centavos() : null;
}

function schse_estado(Declaracion $d, string $linea): ?EstadoLinea
{
    return $d->linea('sch-se', $linea)?->estado;
}

it('escribe el código de formulario sch-se', function () {
    expect((new ScheduleSe)->formulario())->toBe('sch-se');
});

it('calcula el impuesto de autoempleo sin salarios W-2', function () {
    // 2 = 3 = 50.000,00; 4a = 50.000,00 × 0,9235 = 46.175,00 = 4c = 6; 7 = 176.100,00; 8a y 8d no aplican;
    // 9 = 176.100,00; 10 = 46.175,00 × 0,124 = 5.725,70; 11 = 46.175,00 × 0,029 = 1.339,075 → 1.339,08;
    // 12 = 7.064,78; 13 = 3.532,39.
    $d = schse_calcular(schse_entradas(), '50000.00');

    expect(schse_valor($d, '2'))->toBe(5_000_000)
        ->and(schse_valor($d, '3'))->toBe(5_000_000)
        ->and(schse_valor($d, '4a'))->toBe(4_617_500)
        ->and(schse_valor($d, '4c'))->toBe(4_617_500)
        ->and(schse_valor($d, '6'))->toBe(4_617_500)
        ->and(schse_valor($d, '7'))->toBe(17_610_000)
        ->and(schse_estado($d, '8a'))->toBe(EstadoLinea::NoAplica)
        ->and(schse_estado($d, '8b'))->toBe(EstadoLinea::NoAplica)
        ->and(schse_estado($d, '8c'))->toBe(EstadoLinea::NoAplica)
        ->and(schse_estado($d, '8d'))->toBe(EstadoLinea::NoAplica)
        ->and(schse_valor($d, '9'))->toBe(17_610_000)
        ->and(schse_valor($d, '10'))->toBe(572_570)
        ->and(schse_valor($d, '11'))->toBe(133_908)
        ->and(schse_valor($d, '12'))->toBe(706_478)
        ->and(schse_valor($d, '13'))->toBe(353_239);
});

it('con la línea 4c bajo el mínimo no se debe el impuesto y deja escritas las líneas 2 a 4c', function () {
    // 400,00 × 0,9235 = 369,40 < 400: "stop; you don't owe self-employment tax".
    $d = schse_calcular(schse_entradas(), '400.00');

    expect(schse_valor($d, '4c'))->toBe(36_940);

    foreach (['6', '7', '8a', '8d', '9', '10', '11', '12', '13'] as $linea) {
        expect(schse_estado($d, $linea))->toBe(EstadoLinea::NoAplica);
    }
    expect($d->linea('sch-se', '12')->motivo)->toContain('no se debe impuesto de autoempleo');
});

it('con pérdida en el Schedule C la línea 4a copia la 3 y no hay impuesto', function () {
    $d = schse_calcular(schse_entradas(), '-2000.00');

    expect(schse_valor($d, '4a'))->toBe(-200_000)
        ->and(schse_valor($d, '4c'))->toBe(-200_000)
        ->and(schse_estado($d, '12'))->toBe(EstadoLinea::NoAplica)
        ->and(schse_estado($d, '13'))->toBe(EstadoLinea::NoAplica);
});

it('los salarios W-2 de la misma persona reducen la base del Seguro Social y los del cónyuge no', function () {
    // 8a = 150.000,00 (c. 3) + 6.100,00 (c. 7) del contribuyente = 156.100,00 = 8d; el W-2 del cónyuge no cuenta.
    // 9 = 176.100,00 − 156.100,00 = 20.000,00; 6 = 46.175,00; 10 = 20.000,00 × 0,124 = 2.480,00;
    // 11 = 1.339,08; 12 = 3.819,08; 13 = 1.909,54.
    $w2s = [schse_w2(7, 1, '150000.00', '6100.00'), schse_w2(8, 2, '100000.00')];
    $d = schse_calcular(schse_entradas($w2s, conyuge: true), '50000.00');

    expect(schse_valor($d, '8a'))->toBe(15_610_000)
        ->and(schse_valor($d, '8d'))->toBe(15_610_000)
        ->and(schse_valor($d, '9'))->toBe(2_000_000)
        ->and(schse_valor($d, '10'))->toBe(248_000)
        ->and(schse_valor($d, '11'))->toBe(133_908)
        ->and(schse_valor($d, '12'))->toBe(381_908)
        ->and(schse_valor($d, '13'))->toBe(190_954);

    $textos = array_map(fn ($p) => $p->texto, $d->linea('sch-se', '8a')->traza->pasos);
    expect($textos)->toContain('El W-2 #8 es de otra persona: no cuenta en este Schedule SE.');
});

it('con salarios por encima del tope solo queda la parte de Medicare', function () {
    // 8a = 180.000,00 ≥ 176.100,00: 9 = 0 y 10 = 0; 11 = 1.339,08 = 12; 13 = 669,54.
    $d = schse_calcular(schse_entradas([schse_w2(7, 1, '180000.00')]), '50000.00');

    expect(schse_valor($d, '9'))->toBe(0)
        ->and(schse_valor($d, '10'))->toBe(0)
        ->and(schse_valor($d, '11'))->toBe(133_908)
        ->and(schse_valor($d, '12'))->toBe(133_908)
        ->and(schse_valor($d, '13'))->toBe(66_954);
});

it('con 1099 de trabajo independiente de dos personas el Schedule SE sale no soportado', function () {
    $nec = [new DocumentoEntrada('1099-nec', 50, 1, []), new DocumentoEntrada('1099-nec', 51, 2, [])];
    $d = schse_calcular(schse_entradas(conyuge: true, nec: $nec), '50000.00');

    foreach (ScheduleSe::LINEAS as $linea) {
        expect(schse_estado($d, $linea))->toBe(EstadoLinea::NoSoportada);
    }
});

it('un W-2 sin persona deja la línea 8a en falta_dato y arrastra el Seguro Social, no Medicare', function () {
    $d = schse_calcular(schse_entradas([schse_w2(9, null, '30000.00')], conyuge: true), '50000.00');

    expect(schse_estado($d, '8a'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-se', '8a')->motivo)->toBe('Falta saber de quién es el W-2 #9.')
        ->and(schse_estado($d, '9'))->toBe(EstadoLinea::FaltaDato)
        ->and(schse_estado($d, '10'))->toBe(EstadoLinea::FaltaDato)
        ->and(schse_estado($d, '12'))->toBe(EstadoLinea::FaltaDato)
        ->and(schse_estado($d, '13'))->toBe(EstadoLinea::FaltaDato)
        ->and(schse_valor($d, '11'))->toBe(133_908);
});

it('sin la línea 31 del Schedule C todo sale falta_dato', function () {
    $d = schse_calcular(schse_entradas(), null);

    expect(schse_estado($d, '2'))->toBe(EstadoLinea::FaltaDato)
        ->and(schse_estado($d, '13'))->toBe(EstadoLinea::FaltaDato);
});

it('sin negocio (Schedule C que no aplica) el Schedule SE no aplica', function () {
    $d = new Declaracion(2025, 'f6-prueba');
    $d->poner(Linea::noAplica('sch-c', '31'));
    $e = new EntradasDeclaracion(2025, EstadoCivilIrs::Single, personas: [new PersonaEntrada(1, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, 40, true, false)]);
    (new ScheduleSe)->calcular($e, $d);

    foreach (ScheduleSe::LINEAS as $linea) {
        expect(schse_estado($d, $linea))->toBe(EstadoLinea::NoAplica);
    }
});

it('con un K-1 el Schedule SE sale no soportado', function () {
    $d = schse_calcular(schse_entradas(datos: ['k-1:14' => new DatoEntrada(Dinero::deDolares('100.00'), OrigenTraza::Leido, 'k-1:14')]), '50000.00');

    expect(schse_estado($d, '2'))->toBe(EstadoLinea::NoSoportada)
        ->and(schse_estado($d, '12'))->toBe(EstadoLinea::NoSoportada);
});

it('los datos sin validar del Schedule C y del W-2 llegan a las líneas 12 y 13', function () {
    $d = schse_calcular(schse_entradas([schse_w2(7, 1, '10000.00', validado: false)]), '50000.00', ['1099-nec:1']);

    expect($d->linea('sch-se', '4c')->datosSinValidar)->toBe(['1099-nec:1'])
        ->and($d->linea('sch-se', '8a')->datosSinValidar)->toBe(['w2#7:3'])
        ->and($d->linea('sch-se', '12')->usaSinValidar)->toBeTrue()
        ->and($d->linea('sch-se', '12')->datosSinValidar)->toEqualCanonicalizing(['1099-nec:1', 'w2#7:3'])
        ->and($d->linea('sch-se', '13')->usaSinValidar)->toBeTrue();
});

it('con un parámetro pendiente las líneas desde la 4a salen falta_dato con su nombre', function () {
    ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'self_employment_tax')->where('clave', 'tope_salario_social_security')->update(['valor' => null]);
    ParametrosFiscales::invalidate();

    $d = schse_calcular(schse_entradas(), '50000.00');

    expect(schse_valor($d, '3'))->toBe(5_000_000)
        ->and(schse_estado($d, '4a'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('sch-se', '12')->motivo)->toBe('No se puede calcular: falta el parámetro self_employment_tax.tope_salario_social_security de 2025 (Catálogo → Parámetros).');
});

it('la traza lleva referencias y nunca valores sensibles', function () {
    $d = schse_calcular(schse_entradas([schse_w2(7, 1, '20000.00', '500.00')]), '50000.00');

    foreach ($d->lineas('sch-se') as $linea) {
        foreach ($linea->traza->pasos as $paso) {
            if ($paso->referencia !== null) {
                expect($paso->referencia)->toMatch('/^(sch-c:|sch-se:|w2#\d+:[37]$|self_employment_tax\.)/');
            }
        }
    }
});

it('encadena Schedule C, Schedule SE y Schedule 1 (líneas 3 y 15) y Schedule 2 (línea 4)', function () {
    // Schedule C 31 = 50.000,00 → Schedule 1 línea 3; SE 12 = 7.064,78 → Schedule 2 línea 4; SE 13 = 3.532,39 → Schedule 1 línea 15.
    $e = schse_entradas(datos: ['1099-nec:1' => new DatoEntrada(Dinero::deDolares('50000.00'), OrigenTraza::Leido, '1099-nec:1')]);
    $d = new Declaracion(2025, 'f6-prueba');

    (new ScheduleC)->calcular($e, $d);
    (new ScheduleSe)->calcular($e, $d);
    (new Schedule1)->calcular($e, $d);

    expect($d->linea('sch-1', '3')->valor->centavos())->toBe(5_000_000)
        ->and($d->linea('sch-1', '15')->valor->centavos())->toBe(353_239)
        ->and(schse_valor($d, '12'))->toBe(706_478);
});

it('los motivos tienen su texto en inglés', function () {
    app()->setLocale('en');
    $d = schse_calcular(schse_entradas(), '400.00');

    expect($d->linea('sch-se', '12')->motivo)->toBe("Line 4c is less than USD 400,00: no self-employment tax is owed and Schedule SE isn't filed (Schedule SE, line 4c).");
});
