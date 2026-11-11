<?php

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\Deducciones;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RespuestaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Valores esperados, del Form 1040 de 2025 y sus instrucciones (i1040gi--2025.pdf):
 * - Form 1040, p. 2, recuadro "Standard deduction for": soltero o MFS USD 15.750; MFJ o QSS
 *   USD 31.500; HOH USD 23.625. Línea 14 = 12e + 13a + 13b; línea 15 = 11b − 14 (mínimo cero).
 * - i1040gi p. 35, "Standard Deduction Chart for People Who Were Born Before January 2, 1961, or
 *   Were Blind": soltero 1 casilla 17.750; MFJ 1 = 33.100, 2 = 34.700, 4 = 37.900; QSS 1 = 33.100;
 *   MFS 1 = 17.350; HOH 1 = 25.625.
 * - i1040gi p. 35, "Standard Deduction Worksheet for Dependents": línea 2 = ingreso del trabajo
 *   + USD 450 si pasa de USD 900, si no USD 1.350; 4a = la menor entre 2 y la estándar; 4b =
 *   casillas × USD 1.600 (USD 2.000 soltero o HOH); 4c = 4a + 4b.
 * - i1040gi p. 33, línea 12d: "Don't check any boxes for your spouse if your filing status is head
 *   of household"; p. 35, nota de la tabla: con MFS, las casillas del cónyuge solo si no tuvo
 *   ingresos, no presenta y nadie lo declara.
 * - i8995 p. 4: línea 11 del 8995 = 1040 11a − 12e − 13b.
 */

beforeEach(function () {
    app()->setLocale('es');
    $this->seed(ParametrosFiscalesSeeder::class);
});

function ded_persona(int $id, RolPersona $rol, ?int $edad = 40, ?bool $otroPuedeReclamar = false, bool $ssnValido = true): PersonaEntrada
{
    return new PersonaEntrada($id, $rol, ClasificacionPersona::NoDependiente, $edad, $ssnValido, false, otroPuedeReclamar: $otroPuedeReclamar);
}

/**
 * @param  list<PersonaEntrada>|null  $personas
 * @param  array<string, RespuestaEntrada>  $respuestas
 * @param  array<string, DatoEntrada>  $datos
 * @param  array<string, list<DocumentoEntrada>>  $documentos
 */
function ded_entradas(?EstadoCivilIrs $estado = EstadoCivilIrs::Single, ?array $personas = null, array $respuestas = [], array $datos = [], array $documentos = [], int $anio = 2025): EntradasDeclaracion
{
    $personas ??= [ded_persona(1, RolPersona::Contribuyente)];

    return new EntradasDeclaracion($anio, $estado, personas: $personas, datos: $datos, respuestas: $respuestas, documentos: $documentos);
}

/** Lo que dejan escrito las calculadoras anteriores: 1040 1z, 11a y 11b; Schedule 1 líneas 3 y 15; Schedule C 31. */
function ded_declaracion(string $agi = '60000', string $salarios = '60000', string $negocio = 'no_aplica', string $mitadSe = 'no_aplica', int $anio = 2025): Declaracion
{
    $d = new Declaracion($anio, 'f6-prueba');
    $poner = function (string $formulario, string $linea, string $valor) use ($d): void {
        $d->poner($valor === 'no_aplica'
            ? Linea::noAplica($formulario, $linea)
            : Linea::calculada($formulario, $linea, Dinero::deDolares($valor), Traza::vacia()));
    };

    $poner('1040', '1z', $salarios);
    $poner('1040', '11a', $agi);
    $poner('1040', '11b', $agi);
    $poner('sch-1', '3', $negocio);
    $poner('sch-1', '15', $mitadSe);
    $poner('sch-c', '31', $negocio);

    return $d;
}

function ded_calcular(EntradasDeclaracion $e, ?Declaracion $d = null): Declaracion
{
    $d ??= ded_declaracion();
    (new Deducciones)->calcular($e, $d);

    return $d;
}

function ded_valor(Declaracion $d, string $linea, string $formulario = '1040'): int|float|null
{
    $valor = $d->linea($formulario, $linea)?->valor;

    return $valor instanceof Dinero ? $valor->centavos() / 100 : null;
}

function ded_estado(Declaracion $d, string $linea, string $formulario = '1040'): ?EstadoLinea
{
    return $d->linea($formulario, $linea)?->estado;
}

it('escribe en el formulario 1040', function () {
    expect((new Deducciones)->formulario())->toBe('1040');
});

it('deducción estándar por estado civil, sin casillas de 12d', function (EstadoCivilIrs $estado, int $esperado) {
    $personas = [ded_persona(1, RolPersona::Contribuyente), ded_persona(2, RolPersona::Conyuge)];
    $d = ded_calcular(ded_entradas($estado, $personas), ded_declaracion('100000', '100000'));

    expect(ded_estado($d, '12d'))->toBe(EstadoLinea::NoAplica)
        ->and(ded_valor($d, '12e'))->toEqual($esperado)
        ->and(ded_estado($d, '13a'))->toBe(EstadoLinea::NoAplica)
        ->and(ded_estado($d, '13b'))->toBe(EstadoLinea::NoAplica)
        ->and(ded_valor($d, '14'))->toEqual($esperado)
        ->and(ded_valor($d, '15'))->toEqual(100000 - $esperado);
})->with([
    'soltero' => [EstadoCivilIrs::Single, 15750],
    'conjunta' => [EstadoCivilIrs::Mfj, 31500],
    'separada (MFS)' => [EstadoCivilIrs::Mfs, 15750],
    'cabeza de familia' => [EstadoCivilIrs::Hoh, 23625],
    'cónyuge sobreviviente' => [EstadoCivilIrs::Qss, 31500],
]);

it('casillas de 65 años o más: coincide con la Standard Deduction Chart', function (EstadoCivilIrs $estado, int $edadContribuyente, int $edadConyuge, int $adicional, int $estandar) {
    $personas = [ded_persona(1, RolPersona::Contribuyente, $edadContribuyente), ded_persona(2, RolPersona::Conyuge, $edadConyuge)];
    $d = ded_calcular(ded_entradas($estado, $personas), ded_declaracion('200000', '200000'));

    expect($d->linea('1040', '12d')->valor)->toBe((string) $adicional)
        ->and(ded_valor($d, '12e'))->toEqual($estandar);
})->with([
    'soltero de 65' => [EstadoCivilIrs::Single, 65, 40, 1, 17750],
    'conjunta, uno de 70' => [EstadoCivilIrs::Mfj, 70, 50, 1, 33100],
    'conjunta, los dos de 66' => [EstadoCivilIrs::Mfj, 66, 66, 2, 34700],
    'MFS, contribuyente de 65' => [EstadoCivilIrs::Mfs, 65, 30, 1, 17350],
    'HOH de 65 (el cónyuge no cuenta)' => [EstadoCivilIrs::Hoh, 65, 70, 1, 25625],
    'QSS de 68 (el cónyuge no cuenta)' => [EstadoCivilIrs::Qss, 68, 70, 1, 33100],
]);

it('la ceguera de la pregunta A9 suma casillas: conjunta con las cuatro da USD 37.900', function () {
    $personas = [ded_persona(1, RolPersona::Contribuyente, 70), ded_persona(2, RolPersona::Conyuge, 66)];
    $e = ded_entradas(EstadoCivilIrs::Mfj, $personas, ['A9' => new RespuestaEntrada('usted,conyuge')]);
    $d = ded_calcular($e, ded_declaracion('200000', '200000'));

    expect($d->linea('1040', '12d')->valor)->toBe('4')
        ->and(ded_valor($d, '12e'))->toEqual(37900);
});

it('soltero ciego de 40 años: una casilla, USD 17.750', function () {
    $d = ded_calcular(ded_entradas(respuestas: ['A9' => new RespuestaEntrada('usted')]));

    expect($d->linea('1040', '12d')->valor)->toBe('1')
        ->and(ded_valor($d, '12e'))->toEqual(17750);
});

it('sin respuesta de ceguera cuenta solo la edad y lo dice en la traza', function () {
    $d = ded_calcular(ded_entradas(personas: [ded_persona(1, RolPersona::Contribuyente, 70)]));
    $textos = array_map(fn ($p) => $p->texto, $d->linea('1040', '12d')->traza->pasos);

    expect($d->linea('1040', '12d')->valor)->toBe('1')
        ->and(implode(' ', $textos))->toContain('La pregunta A9 (ceguera) no está respondida');
});

it('una respuesta de ceguera que no se entiende es falta_dato', function () {
    $d = ded_calcular(ded_entradas(respuestas: ['A9' => new RespuestaEntrada('quizas')]));

    expect(ded_estado($d, '12d'))->toBe(EstadoLinea::FaltaDato)
        ->and(ded_estado($d, '12e'))->toBe(EstadoLinea::FaltaDato)
        ->and(ded_estado($d, '15'))->toBe(EstadoLinea::FaltaDato);
});

it('con MFS y el cónyuge de 65 o más lo revisa el preparador', function () {
    $personas = [ded_persona(1, RolPersona::Contribuyente, 50), ded_persona(2, RolPersona::Conyuge, 70)];
    $d = ded_calcular(ded_entradas(EstadoCivilIrs::Mfs, $personas));

    expect(ded_estado($d, '12d'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '12d')->motivo)->toContain('Con declaración separada (MFS)');
});

it('con 64 años al cierre la 12d espera la fecha de nacimiento', function () {
    $d = ded_calcular(ded_entradas(personas: [ded_persona(1, RolPersona::Contribuyente, 64)]));

    expect(ded_estado($d, '12d'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '12d')->motivo)->toContain('64 años')
        ->and(ded_estado($d, '15'))->toBe(EstadoLinea::FaltaDato);
});

it('sin la edad del contribuyente la 12d es falta_dato', function () {
    $d = ded_calcular(ded_entradas(personas: [ded_persona(1, RolPersona::Contribuyente, null)]));

    expect($d->linea('1040', '12d')->motivo)->toBe('Falta la fecha de nacimiento del contribuyente (pregunta A1.1).');
});

it('dependiente de otro: hoja del dependiente según el ingreso del trabajo', function (string $salarios, int $edad, int $esperado) {
    $e = ded_entradas(personas: [ded_persona(1, RolPersona::Contribuyente, $edad, otroPuedeReclamar: true)]);
    $d = ded_calcular($e, ded_declaracion($salarios, $salarios));

    expect(ded_valor($d, '12e'))->toEqual($esperado);
})->with([
    // 500 + 450 = 950 < 1.350 → 1.350.
    'ingreso de 500' => ['500', 30, 1350],
    // 3.000 + 450 = 3.450.
    'ingreso de 3.000' => ['3000', 30, 3450],
    // 20.000 + 450 = 20.450 > 15.750 → 15.750.
    'ingreso de 20.000' => ['20000', 30, 15750],
    // 4a = 3.450; 4b = 1 × 2.000; 4c = 5.450.
    'ingreso de 3.000 y 66 años' => ['3000', 66, 5450],
]);

it('dependiente de otro con negocio: el ingreso del trabajo suma la línea 3 del Schedule 1 y resta la 15', function () {
    // 1z 1.000 + negocio 4.000 − 282,60 = 4.717,40; + 450 = 5.167,40.
    $e = ded_entradas(personas: [ded_persona(1, RolPersona::Contribuyente, 20, otroPuedeReclamar: true)]);
    $d = ded_calcular($e, ded_declaracion('4717.40', '1000', '4000', '282.60'));

    expect(ded_valor($d, '12e'))->toEqual(5167.4);
});

it('si no se sabe si alguien puede declararlo, usa la deducción normal y lo dice', function () {
    $d = ded_calcular(ded_entradas(personas: [ded_persona(1, RolPersona::Contribuyente, otroPuedeReclamar: null)]));
    $textos = array_map(fn ($p) => $p->texto, $d->linea('1040', '12e')->traza->pasos);

    expect(ded_valor($d, '12e'))->toEqual(15750)
        ->and(implode(' ', $textos))->toContain('No se sabe si otra persona puede declarar al contribuyente');
});

it('con documentos de Schedule A la 12e no se soporta y arrastra 14 y 15', function () {
    $datos = ['1098:1' => new DatoEntrada(Dinero::deDolares('9000'), OrigenTraza::Leido, '1098#2:1')];
    $d = ded_calcular(ded_entradas(datos: $datos));

    expect(ded_estado($d, '12e'))->toBe(EstadoLinea::NoSoportada)
        ->and($d->linea('1040', '12e')->motivo)->toContain('1098')
        ->and(ded_estado($d, '14'))->toBe(EstadoLinea::NoSoportada)
        ->and(ded_estado($d, '15'))->toBe(EstadoLinea::NoSoportada);
});

it('la línea 15 no baja de cero', function () {
    $d = ded_calcular(ded_entradas(), ded_declaracion('10000', '10000'));

    expect(ded_valor($d, '14'))->toEqual(15750)
        ->and(ded_valor($d, '15'))->toEqual(0);
});

it('sin estado civil las líneas son falta_dato', function () {
    $d = ded_calcular(ded_entradas(null));

    expect($d->linea('1040', '12d')->motivo)->toBe('Falta decidir el estado civil en Revisión.')
        ->and(ded_estado($d, '12e'))->toBe(EstadoLinea::FaltaDato)
        ->and(ded_estado($d, '15'))->toBe(EstadoLinea::FaltaDato);
});

it('con negocio corre el 8995 en orden: 13a sale del ingreso después de 12e', function () {
    // Schedule C 50.000, mitad del SE 3.532,50, 11a = 46.467,50 (sin salarios).
    // 8995: 11 = 46.467,50 − 15.750 = 30.717,50; 15 = min(9.293,50; 6.143,50) = 6.143,50.
    // 14 = 15.750 + 6.143,50 = 21.893,50; 15 = 46.467,50 − 21.893,50 = 24.574.
    $d = ded_calcular(ded_entradas(), ded_declaracion('46467.50', '0', '50000', '3532.50'));

    expect(ded_valor($d, '15', 'f8995'))->toEqual(6143.5)
        ->and(ded_valor($d, '13a'))->toEqual(6143.5)
        ->and(ded_valor($d, '14'))->toEqual(21893.5)
        ->and(ded_valor($d, '15'))->toEqual(24574);
});

it('con propinas corre el Schedule 1-A: 13b entra a la 14 y al ingreso del 8995', function () {
    // Constancia de propinas 5.000, MFJ, MAGI 120.000 < 300.000 → 13b = 5.000.
    // 8995: 11 = 120.000 − 31.500 − 5.000 = 83.500; Schedule C 10.000 − 706,50 = 9.293,50; 5 = 1.858,70; 14 = 16.700; 15 = 1.858,70.
    // 14 = 31.500 + 1.858,70 + 5.000 = 38.358,70; 15 = 120.000 − 38.358,70 = 81.641,30.
    $personas = [ded_persona(1, RolPersona::Contribuyente), ded_persona(2, RolPersona::Conyuge)];
    $constancia = new DocumentoEntrada('constancia-horas-propinas', 7, 2, [
        'propinas-calificadas' => new DatoEntrada(Dinero::deDolares('5000'), OrigenTraza::Leido, 'constancia-horas-propinas#7:propinas-calificadas'),
    ]);
    $e = ded_entradas(EstadoCivilIrs::Mfj, $personas, documentos: ['constancia-horas-propinas' => [$constancia]]);
    $d = ded_calcular($e, ded_declaracion('120000', '110706.50', '10000', '706.50'));

    expect(ded_valor($d, '38', 'sch-1a'))->toEqual(5000)
        ->and(ded_valor($d, '13b'))->toEqual(5000)
        ->and(ded_valor($d, '11', 'f8995'))->toEqual(83500)
        ->and(ded_valor($d, '13a'))->toEqual(1858.7)
        ->and(ded_valor($d, '14'))->toEqual(38358.7)
        ->and(ded_valor($d, '15'))->toEqual(81641.3);
});

it('un vehículo nuevo deja 13b, 14 y 15 en falta_dato (ensamblaje en EE. UU.)', function () {
    $prestamo = new DocumentoEntrada('estado-prestamo-vehiculo', 9, 1, [
        'intereses-pagados' => new DatoEntrada(Dinero::deDolares('1500'), OrigenTraza::Leido, 'estado-prestamo-vehiculo#9:intereses-pagados'),
        'vehiculo-nuevo' => new DatoEntrada('si', OrigenTraza::Leido, 'estado-prestamo-vehiculo#9:vehiculo-nuevo'),
    ]);
    $d = ded_calcular(ded_entradas(documentos: ['estado-prestamo-vehiculo' => [$prestamo]]));

    expect(ded_estado($d, '13b'))->toBe(EstadoLinea::FaltaDato)
        ->and(ded_estado($d, '14'))->toBe(EstadoLinea::FaltaDato)
        ->and(ded_estado($d, '15'))->toBe(EstadoLinea::FaltaDato);
});

it('con el adicional por edad pendiente la 12d es falta_dato con el nombre del parámetro', function () {
    ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'deduccion_estandar')->where('clave', 'adicional_65_ciego_soltero_hoh')->update(['valor' => null]);
    ParametrosFiscales::invalidate();

    $d = ded_calcular(ded_entradas(personas: [ded_persona(1, RolPersona::Contribuyente, 70)]));

    expect($d->linea('1040', '12d')->motivo)->toBe('No se puede calcular: falta el parámetro deduccion_estandar.adicional_65_ciego_soltero_hoh de 2025 (Catálogo → Parámetros).')
        ->and(ded_estado($d, '15'))->toBe(EstadoLinea::FaltaDato);
});

it('en 2026 sin parámetros cargados la 12e es falta_dato con el nombre del parámetro', function () {
    $d = ded_calcular(ded_entradas(anio: 2026), ded_declaracion(anio: 2026));

    expect(ded_estado($d, '12e'))->toBe(EstadoLinea::FaltaDato)
        ->and($d->linea('1040', '12e')->motivo)->toBe('No se puede calcular: falta el parámetro deduccion_estandar.monto_soltero de 2026 (Catálogo → Parámetros).');
});

it('la traza de 12d lleva referencias a la persona y a la pregunta, nunca la fecha de nacimiento', function () {
    $d = ded_calcular(ded_entradas(personas: [ded_persona(1, RolPersona::Contribuyente, 70)], respuestas: ['A9' => new RespuestaEntrada('no')]));
    $referencias = array_values(array_filter(array_map(fn ($p) => $p->referencia, $d->linea('1040', '12d')->traza->pasos)));

    expect($referencias)->toContain('persona:1:edad')
        ->and($referencias)->toContain('A9')
        ->and($d->linea('1040', '12d')->valor)->toBe('1');
});

it('en inglés los motivos salen de lang/calculo/deducciones', function () {
    app()->setLocale('en');
    $datos = ['1098:1' => new DatoEntrada(Dinero::deDolares('9000'), OrigenTraza::Leido, '1098#2:1')];
    $d = ded_calcular(ded_entradas(datos: $datos));

    expect($d->linea('1040', '12e')->motivo)->toBe('There are itemized deduction documents (1098): the preparer compares the standard deduction with Schedule A, which arrives in F7.');
});

it('con MFS sin la edad del cónyuge no se frena: su casilla no cuenta y la traza lo dice', function () {
    $personas = [ded_persona(1, RolPersona::Contribuyente, 50), ded_persona(2, RolPersona::Conyuge, null)];
    $d = ded_calcular(ded_entradas(EstadoCivilIrs::Mfs, $personas));

    expect(ded_estado($d, '12d'))->toBe(EstadoLinea::NoAplica)
        ->and(ded_valor($d, '12e'))->toEqual(15750);
});
