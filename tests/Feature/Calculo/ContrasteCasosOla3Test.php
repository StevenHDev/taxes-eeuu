<?php

namespace Tests\Feature\Calculo;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Deducciones;
use App\Services\Calculo\Formularios\Form2441;
use App\Services\Calculo\Formularios\ImpuestoYSchedule2;
use App\Services\Calculo\Formularios\Ingresos1040;
use App\Services\Calculo\Formularios\PagosYResultado;
use App\Services\Calculo\Formularios\Schedule1;
use App\Services\Calculo\Formularios\Schedule3;
use App\Services\Calculo\Formularios\Schedule8812;
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
use App\Services\Calculo\Nucleo\RespuestaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * Doble lectura del cierre de la ola 3 (F6, 6.9b): los casos de `tests/Fixtures/irs/casos/2025/` los escribió
 * quien no vio las calculadoras. Aquí se arman a mano las entradas del motor desde las `entradas` del caso (que
 * están en palabras), se corren las calculadoras y se compara cada línea de `cubre`.
 *
 * Convención de comparación: el caso va en dólares enteros ("Rounding Off to Whole Dollars", i1040gi p. 23) y el
 * motor en centavos, así que el valor del motor se redondea a dólares con `Dinero::aDolares()` (50 centavos o más
 * sube). Una línea "no aplica" del motor cuenta como "0" (en el formulario queda en blanco). Las líneas que no son
 * montos (conteos, decimales) se comparan como texto.
 *
 * Si una línea no coincide, no se toca ni la calculadora ni el caso: la diferencia se revisa contra el PDF fijado
 * y, si está confirmada, queda listada en `$confirmadas` y la prueba se marca incompleta con el motivo.
 *
 * `schedule-se-borde-400-redondeo` no se corre: no declara cobertura (es ambiguo a propósito).
 */
class ContrasteCasosOla3Test extends TestCase
{
    use RefreshDatabase;

    private const CONTRIBUYENTE = 1;

    private const CONYUGE = 2;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        $this->seed(ParametrosFiscalesSeeder::class);
    }

    // ── Schedule C ──

    public function test_schedule_c_ingresos_y_gastos(): void
    {
        $caso = $this->caso('schedule-c-ingresos-y-gastos');

        // Los estacionamientos y peajes (USD 85) no tienen dónde entrar: con millas, una suma en la línea 9 de los
        // registros hace que el motor pida elegir entre tarifa estándar y gastos reales (falta_dato).
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 40)],
            datos: ['1099-nec:1' => $this->dato('18000'), '1099-k:1a' => $this->dato('9500')],
            registros: [
                '1-sin-formulario' => '2500', '2' => '0', '8' => '600', 'millas' => '3000', '15' => '900',
                '18' => '450', '22' => '1200', '24b' => '640', '25' => '360',
            ],
            documentos: [
                '1099-nec' => [$this->doc('1099-nec', 10, self::CONTRIBUYENTE), $this->doc('1099-nec', 11, self::CONTRIBUYENTE)],
                '1099-k' => [$this->doc('1099-k', 12, self::CONTRIBUYENTE)],
            ],
        );

        $d = $this->correr($e, [new ScheduleC]);

        $motivo = 'i1040sc p. 6 (Line 9): "Add to this amount your parking fees and tolls". El motor solo multiplica las millas y deja los USD 85 al preparador (no hay entrada para sumarlos).';
        $this->contrastar($caso, $d, [
            'sch-c:9' => $motivo,
            'sch-c:28' => 'Arrastra la línea 9 (faltan los USD 85 de estacionamiento y peajes).',
            'sch-c:29' => 'Arrastra la línea 9.',
            'sch-c:31' => 'Arrastra la línea 9.',
        ]);
    }

    public function test_schedule_c_oficina_simplificada(): void
    {
        $this->oficinaEnCasa('schedule-c-oficina-simplificada', '40000', '8000', '360', '1500');
    }

    public function test_schedule_c_oficina_limitada_por_ganancia(): void
    {
        $this->oficinaEnCasa('schedule-c-oficina-limitada-por-ganancia', '6000', '5200', '200', '1200');
    }

    private function oficinaEnCasa(string $codigo, string $ingreso, string $gastos, string $piesOficina, string $piesCasa): void
    {
        $caso = $this->caso($codigo);

        // El caso da el total de gastos de las líneas 8 a 27b; se pone en la 27b (otros gastos), que suma igual en la 28.
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 40)],
            datos: ['1099-nec:1' => $this->dato($ingreso)],
            registros: ['27b' => $gastos, 'pies-oficina' => $piesOficina, 'pies-casa' => $piesCasa],
            documentos: ['1099-nec' => [$this->doc('1099-nec', 10, self::CONTRIBUYENTE)]],
        );

        $this->contrastar($caso, $this->correr($e, [new ScheduleC]));
    }

    // ── Schedule SE ──

    public function test_schedule_se_bajo_minimo(): void
    {
        $this->scheduleSe('schedule-se-bajo-minimo', '432', null);
    }

    public function test_schedule_se_con_w2(): void
    {
        $this->scheduleSe('schedule-se-con-w2', '28000', '151000');
    }

    public function test_schedule_se_sobre_tope(): void
    {
        $this->scheduleSe('schedule-se-sobre-tope', '202000', null);
    }

    /** La ganancia de la línea 31 sale de un 1099-NEC sin gastos (la línea 31 queda igual a lo que da el caso). */
    private function scheduleSe(string $codigo, string $ganancia, ?string $salariosSeguroSocial): void
    {
        $caso = $this->caso($codigo);
        $documentos = ['1099-nec' => [$this->doc('1099-nec', 10, self::CONTRIBUYENTE)]];
        $datos = ['1099-nec:1' => $this->dato($ganancia)];

        if ($salariosSeguroSocial !== null) {
            // W-2 casilla 3 del caso y casilla 7 (propinas) en cero.
            $documentos['w2'] = [$this->doc('w2', 20, self::CONTRIBUYENTE, ['3' => $salariosSeguroSocial, '7' => '0'])];
            $datos['w2:3'] = $this->dato($salariosSeguroSocial);
        }

        $e = $this->entradas(EstadoCivilIrs::Single, [$this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 40)], datos: $datos, documentos: $documentos);

        $d = $this->correr($e, [new ScheduleC, new ScheduleSe, new Schedule1, new ImpuestoYSchedule2]);

        $this->assertSame(Dinero::deDolares($ganancia)->centavos(), $d->linea('sch-c', '31')?->valor?->centavos(), "{$codigo}: la línea 31 del Schedule C no es la del caso");
        $this->contrastar($caso, $d);
    }

    // ── Deducción estándar ──

    public function test_deduccion_estandar_65_mfj(): void
    {
        $caso = $this->caso('deduccion-estandar-65-mfj');

        // Nacidos en 1955 y 1958: 70 y 67 años al cierre de 2025. Nadie los puede reclamar (12a y 12b sin marcar).
        $e = $this->entradas(EstadoCivilIrs::Mfj, [
            $this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 70),
            $this->adulto(self::CONYUGE, RolPersona::Conyuge, 67),
        ], respuestas: ['A9' => new RespuestaEntrada('no')]);

        $d = new Declaracion(2025, 'contraste');
        // Sin ingresos en el caso: se ponen a mano para que el Schedule 1-A y el 8995 no frenen la 12e (no se contrastan).
        $d->poner(Linea::calculada('1040', '11a', Dinero::deCentavos(0), Traza::vacia()));
        $d->poner(Linea::calculada('1040', '11b', Dinero::deCentavos(0), Traza::vacia()));
        $this->correr($e, [new ScheduleC, new ScheduleSe, new Schedule1, new Deducciones], $d);

        $this->contrastar($caso, $d);
    }

    public function test_deduccion_estandar_dependiente(): void
    {
        $caso = $this->caso('deduccion-estandar-dependiente');

        $e = $this->entradas(EstadoCivilIrs::Single, [$this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 19, otroPuedeReclamar: true)],
            datos: ['w2:1' => $this->dato('4000')],
            respuestas: ['A9' => new RespuestaEntrada('no')],
            documentos: ['w2' => [$this->doc('w2', 20, self::CONTRIBUYENTE, ['1' => '4000'])]],
        );

        $d = $this->correr($e, [new ScheduleC, new ScheduleSe, new Schedule1, $this->beneficios2441(), new Ingresos1040, new Deducciones]);

        $this->contrastar($caso, $d);
    }

    // ── Form 8995 ──

    public function test_form8995_bajo_umbral(): void
    {
        $caso = $this->caso('form8995-bajo-umbral');

        $e = $this->entradas(EstadoCivilIrs::Single, [$this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 40)],
            datos: ['w2:1' => $this->dato('40000'), 'w2:3' => $this->dato('40000'), '1099-nec:1' => $this->dato('18400')],
            respuestas: ['A9' => new RespuestaEntrada('no')],
            documentos: [
                'w2' => [$this->doc('w2', 20, self::CONTRIBUYENTE, ['1' => '40000', '3' => '40000'])],
                '1099-nec' => [$this->doc('1099-nec', 10, self::CONTRIBUYENTE)],
            ],
        );

        $d = $this->correr($e, [new ScheduleC, new ScheduleSe, new Schedule1, $this->beneficios2441(), new Ingresos1040, new Deducciones]);

        $this->contrastar($caso, $d);
    }

    // ── Schedule 8812 ──

    public function test_schedule_8812_dos_hijos(): void
    {
        $caso = $this->caso('schedule-8812-dos-hijos');

        $e = $this->entradas(EstadoCivilIrs::Mfj, [
            $this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 40),
            $this->adulto(self::CONYUGE, RolPersona::Conyuge, 38),
            $this->hijo(3, 6),
            $this->hijo(4, 10),
        ], datos: ['w2:1' => $this->dato('90000')],
            respuestas: ['A9' => new RespuestaEntrada('no')],
            documentos: ['w2' => [$this->doc('w2', 20, self::CONTRIBUYENTE, ['1' => '90000'])]],
        );

        $d = $this->correr($e, $this->cadenaCompleta());

        $this->contrastar($caso, $d);
    }

    public function test_schedule_8812_reduccion_magi(): void
    {
        $caso = $this->caso('schedule-8812-reduccion-magi');

        $madre = new PersonaEntrada(4, RolPersona::Dependiente, ClasificacionPersona::ParienteCalificado, 70, true, false,
            esEstudiante: false, incapacitado: false, mesesConviviendo: 12, parentesco: 'padre', otroPuedeReclamar: false);

        $e = $this->entradas(EstadoCivilIrs::Hoh, [
            $this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 45),
            $this->hijo(3, 12),
            $madre,
        ], datos: ['w2:1' => $this->dato('213450')],
            respuestas: ['A9' => new RespuestaEntrada('no')],
            documentos: ['w2' => [$this->doc('w2', 20, self::CONTRIBUYENTE, ['1' => '213450'])]],
        );

        $d = $this->correr($e, $this->cadenaCompleta());

        $this->contrastar($caso, $d);
    }

    public function test_schedule_8812_actc_limitado(): void
    {
        $caso = $this->caso('schedule-8812-actc-limitado');

        $e = $this->entradas(EstadoCivilIrs::Hoh, [
            $this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 35),
            $this->hijo(3, 5),
            $this->hijo(4, 8),
        ], datos: ['w2:1' => $this->dato('21000')],
            respuestas: ['A9' => new RespuestaEntrada('no')],
            documentos: ['w2' => [$this->doc('w2', 20, self::CONTRIBUYENTE, ['1' => '21000'])]],
        );

        $d = $this->correr($e, $this->cadenaCompleta());

        $this->contrastar($caso, $d);
    }

    // ── Form 2441 ──

    public function test_form2441_dos_personas_mfj(): void
    {
        $caso = $this->caso('form2441-dos-personas-mfj');

        $e = $this->entradas(EstadoCivilIrs::Mfj, [
            $this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 40),
            $this->adulto(self::CONYUGE, RolPersona::Conyuge, 38),
            $this->hijo(3, 3),
            $this->hijo(4, 6),
        ], datos: ['w2:1' => $this->dato('57000')],
            respuestas: ['A9' => new RespuestaEntrada('no')],
            documentos: [
                'w2' => [
                    $this->doc('w2', 20, self::CONTRIBUYENTE, ['1' => '52000', '10' => '0']),
                    $this->doc('w2', 21, self::CONYUGE, ['1' => '5000', '10' => '0']),
                ],
                'recibo-cuidado' => [$this->recibo(30, 3, '4500'), $this->recibo(31, 4, '3000')],
            ],
        );

        $d = $this->correr($e, [
            new ScheduleC, new ScheduleSe, new Schedule1, $this->beneficios2441(), new Ingresos1040, new Deducciones,
            new ImpuestoYSchedule2, new Form2441, new Schedule3,
        ]);

        $this->contrastar($caso, $d);
    }

    public function test_form2441_conyuge_sin_ingreso(): void
    {
        $caso = $this->caso('form2441-conyuge-sin-ingreso');

        // La cónyuge no trabajó ni fue estudiante ni estuvo incapacitada: se dice en su PersonaEntrada (C7 y C8).
        $conyuge = new PersonaEntrada(self::CONYUGE, RolPersona::Conyuge, ClasificacionPersona::NoDependiente, 38, true, false,
            esEstudiante: false, incapacitado: false, otroPuedeReclamar: false);

        $e = $this->entradas(EstadoCivilIrs::Mfj, [
            $this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 40),
            $conyuge,
            $this->hijo(3, 4),
        ], datos: ['w2:1' => $this->dato('45000')],
            respuestas: ['A9' => new RespuestaEntrada('no')],
            documentos: [
                'w2' => [$this->doc('w2', 20, self::CONTRIBUYENTE, ['1' => '45000', '10' => '0'])],
                'recibo-cuidado' => [$this->recibo(30, 3, '3500')],
            ],
        );

        $d = $this->correr($e, [
            new ScheduleC, new ScheduleSe, new Schedule1, $this->beneficios2441(), new Ingresos1040, new Deducciones,
            new ImpuestoYSchedule2, new Form2441, new Schedule3,
        ]);

        $motivo = 'i2441 p. 4 (Line 5 / "Spouse Who Was a Student or Disabled"): sin ingreso del trabajo y sin ser estudiante ni incapacitada, el ingreso de la cónyuge es 0 y el crédito es 0. El motor no lee `esEstudiante`/`incapacitado` del cónyuge y sale falta_dato (lo confirma el preparador).';
        $this->contrastar($caso, $d);
    }

    // ── Pagos y resultado ──

    public function test_pagos_estimados_y_retenciones(): void
    {
        $caso = $this->caso('pagos-estimados-y-retenciones');

        $e = $this->entradas(EstadoCivilIrs::Single, [$this->adulto(self::CONTRIBUYENTE, RolPersona::Contribuyente, 40)],
            datos: [
                'w2:1' => $this->dato('60000'), 'w2:2' => $this->dato('4100'),
                '1099-int:1' => $this->dato('300'), '1099-int:4' => $this->dato('72'),
            ],
            respuestas: ['A9' => new RespuestaEntrada('no')],
            documentos: [
                'w2' => [$this->doc('w2', 20, self::CONTRIBUYENTE, ['1' => '60000', '2' => '4100'])],
                'comprobantes-pagos' => [
                    $this->comprobante(40, '2024', '600'),
                    $this->comprobante(41, '2025', '500'),
                    $this->comprobante(42, '2025', '500'),
                    $this->comprobante(43, '2025', '500'),
                    $this->comprobante(44, '2025', '500'),
                ],
            ],
        );

        // Con un 1099-INT el motor deja fuera del piloto la línea 9 (Ingresos1040) y el EIC: el ingreso gravable
        // (dato del caso, 44.550) y la 27a (el caso dice que no hay EIC) se ponen a mano; no se contrastan.
        $d = new Declaracion(2025, 'contraste');
        $d->poner(Linea::calculada('1040', '11b', Dinero::deDolares('60300'), Traza::vacia()));
        $d->poner(Linea::calculada('1040', '15', Dinero::deDolares('44550'), Traza::vacia()));
        $d->poner(Linea::noAplica('1040', '27a', 'Sin EIC (dato del caso).'));
        $this->correr($e, [new ScheduleC, new ScheduleSe, new Schedule8812, new Form2441, new Schedule3, new ImpuestoYSchedule2, new PagosYResultado], $d);

        $this->assertSame('5111', $this->enDolares($d, '1040', '16'), 'La línea 16 (Tax Table) no es la del caso');
        $pendientes = $this->contrastar($caso, $d, seguir: true);

        // Segunda pasada: con la línea 24 del caso puesta a mano, el resultado (34 a 37) sí se puede contrastar.
        $d->poner(Linea::calculada('1040', '24', Dinero::deDolares('5111'), Traza::vacia()));
        (new PagosYResultado)->calcular($e, $d);
        $this->assertSame(
            ['34' => '1061', '35a' => '1061', '36' => '0', '37' => '0'],
            ['34' => $this->enDolares($d, '1040', '34'), '35a' => $this->enDolares($d, '1040', '35a'), '36' => $this->enDolares($d, '1040', '36'), '37' => $this->enDolares($d, '1040', '37')],
            'Con la línea 24 del caso, el resultado no coincide',
        );

        if ($pendientes !== []) {
            $this->markTestIncomplete($caso['codigo'].' — '.implode(' | ', $pendientes).' | Con la 24 del caso puesta a mano, 34, 35a, 36 y 37 coinciden.');
        }
    }

    // ── Apoyo ──

    /** @return array<string, mixed> */
    private function caso(string $codigo): array
    {
        $caso = CasosIrs::casos(2025)[$codigo] ?? null;
        $this->assertNotNull($caso, "No existe el caso {$codigo}");

        return $caso;
    }

    /**
     * Compara cada línea de `cubre`. Las que están en `$confirmadas` (clave "formulario:línea" => motivo) pueden no
     * coincidir: si alguna sigue sin coincidir, la prueba queda incompleta con el motivo; todas las demás deben coincidir.
     *
     * @param  array<string, mixed>  $caso
     * @param  array<string, string>  $confirmadas
     * @return list<string> las confirmadas que siguen sin coincidir (con `$seguir`, la prueba sigue y las marca quien llama)
     */
    private function contrastar(array $caso, Declaracion $d, array $confirmadas = [], bool $seguir = false): array
    {
        $diferencias = [];
        $pendientes = [];

        foreach ($caso['cubre'] as $clave) {
            [$formulario, $linea] = explode(':', $clave, 2);
            $esperado = (string) ($caso['lineas'][$formulario][$linea] ?? '(sin valor en el caso)');
            $motor = $this->enDolares($d, $formulario, $linea);

            if ($esperado === $motor) {
                continue;
            }

            if (isset($confirmadas[$clave])) {
                $pendientes[] = "{$clave}: caso {$esperado}, motor {$motor}. {$confirmadas[$clave]}";

                continue;
            }

            $diferencias[$clave] = ['caso' => $esperado, 'motor' => $motor];
        }

        $this->assertSame([], $diferencias, "{$caso['codigo']}: líneas que no coinciden con el caso del IRS");

        if ($pendientes !== [] && ! $seguir) {
            $this->markTestIncomplete($caso['codigo'].' — '.implode(' | ', $pendientes));
        }

        return $pendientes;
    }

    /** El valor del motor como lo escribe el caso: dólares enteros, texto tal cual, "0" si no aplica. */
    private function enDolares(Declaracion $d, string $formulario, string $linea): string
    {
        $l = $d->linea($formulario, $linea);

        if ($l === null) {
            return '(sin escribir)';
        }

        return match ($l->estado) {
            EstadoLinea::NoAplica => '0',
            EstadoLinea::Calculada => $l->valor instanceof Dinero
                ? (string) intdiv($l->valor->aDolares()->centavos(), 100)
                : (is_bool($l->valor) ? ($l->valor ? 'si' : 'no') : (string) $l->valor),
            default => $l->estado->value.' ('.$l->motivo.')',
        };
    }

    /** @param  list<object>  $calculadoras */
    private function correr(EntradasDeclaracion $e, array $calculadoras, ?Declaracion $d = null): Declaracion
    {
        $d ??= new Declaracion(2025, 'contraste');

        foreach ($calculadoras as $calculadora) {
            is_callable($calculadora) ? $calculadora($e, $d) : $calculadora->calcular($e, $d);
        }

        return $d;
    }

    /** Orden de las subtareas, sin el EIC ni el 8962 (ningún caso del lote los necesita). */
    private function cadenaCompleta(): array
    {
        return [
            new ScheduleC, new ScheduleSe, new Schedule1, $this->beneficios2441(), new Ingresos1040, new Deducciones,
            new ImpuestoYSchedule2, new Form2441, new Schedule3, $this->sinEic(), new Schedule8812, new ImpuestoYSchedule2,
            new PagosYResultado,
        ];
    }

    private function beneficios2441(): callable
    {
        return fn (EntradasDeclaracion $e, Declaracion $d) => (new Form2441)->calcularBeneficios($e, $d);
    }

    /** Los casos del 8812 no contrastan el EIC; la 27a queda "no aplica" para que la línea 32 se pueda sumar. */
    private function sinEic(): callable
    {
        return fn (EntradasDeclaracion $e, Declaracion $d) => $d->poner(Linea::noAplica('1040', '27a', 'El EIC no es parte de estos casos.'));
    }

    /**
     * @param  list<PersonaEntrada>  $personas
     * @param  array<string, DatoEntrada>  $datos
     * @param  array<string, string>  $registros
     * @param  array<string, RespuestaEntrada>  $respuestas
     * @param  array<string, list<DocumentoEntrada>>  $documentos
     */
    private function entradas(EstadoCivilIrs $estado, array $personas, array $datos = [], array $registros = [], array $respuestas = [], array $documentos = []): EntradasDeclaracion
    {
        return new EntradasDeclaracion(
            2025,
            $estado,
            personas: $personas,
            datos: $datos,
            sumasRegistros: array_map(fn (string $v): Dinero => Dinero::deDolares($v), $registros),
            respuestas: $respuestas,
            documentos: $documentos,
        );
    }

    private function adulto(int $id, RolPersona $rol, int $edad, bool $otroPuedeReclamar = false): PersonaEntrada
    {
        return new PersonaEntrada($id, $rol, ClasificacionPersona::NoDependiente, $edad, true, false, otroPuedeReclamar: $otroPuedeReclamar);
    }

    private function hijo(int $id, int $edad): PersonaEntrada
    {
        return new PersonaEntrada($id, RolPersona::Dependiente, ClasificacionPersona::HijoCalificado, $edad, true, false,
            esEstudiante: false, incapacitado: false, mesesConviviendo: 12, parentesco: 'hijo', otroPuedeReclamar: false);
    }

    private function dato(string $dolares): DatoEntrada
    {
        return new DatoEntrada(Dinero::deDolares($dolares), OrigenTraza::Leido, null);
    }

    /** @param  array<string, string>  $casillas  montos en dólares */
    private function doc(string $codigo, int $itemId, ?int $personaId, array $casillas = []): DocumentoEntrada
    {
        return new DocumentoEntrada($codigo, $itemId, $personaId, array_map(fn (string $v): DatoEntrada => $this->dato($v), $casillas));
    }

    private function recibo(int $itemId, int $personaId, string $monto): DocumentoEntrada
    {
        return new DocumentoEntrada('recibo-cuidado', $itemId, $personaId, [
            'monto-pagado' => $this->dato($monto),
            'proveedor-nombre' => new DatoEntrada('Guardería', OrigenTraza::Leido),
            'proveedor-tin' => new DatoEntrada('12-3456789', OrigenTraza::Leido),
            'periodo-desde' => new DatoEntrada('2025-01-01', OrigenTraza::Leido),
            'periodo-hasta' => new DatoEntrada('2025-12-31', OrigenTraza::Leido),
        ]);
    }

    private function comprobante(int $itemId, string $anio, string $monto): DocumentoEntrada
    {
        return new DocumentoEntrada('comprobantes-pagos', $itemId, self::CONTRIBUYENTE, [
            'tipo-pago' => new DatoEntrada('estimado', OrigenTraza::Leido),
            'anio-aplicado' => new DatoEntrada($anio, OrigenTraza::Leido),
            'monto' => $this->dato($monto),
        ]);
    }
}
