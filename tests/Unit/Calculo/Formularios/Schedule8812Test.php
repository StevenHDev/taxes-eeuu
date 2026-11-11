<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\Schedule8812;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 6.4f: Schedule 8812 (crédito por hijos, por otros dependientes y adicional) y la línea 19 del 1040.
 *
 * Las cifras se arman a mano con el Schedule 8812 de 2025 (p. 1, líneas 1 a 14; p. 2, líneas 16a a 27) y sus
 * instrucciones (i1040s8 de 2025: SSN p. 1, edad p. 2, hoja A p. 4, Earned Income Worksheet p. 8). USD 2.200 por
 * hijo (línea 5), USD 500 por otro dependiente (línea 7), umbral USD 400.000 conjunta y 200.000 los demás (línea 9),
 * 5 % por cada USD 1.000 (líneas 10 y 11), USD 1.700 por hijo (línea 16b), umbral de USD 2.500 y 15 % (líneas 19 y 20).
 */
class Schedule8812Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    public function test_un_hijo_da_2200_y_pasa_a_la_linea_19_del_1040(): void
    {
        // 8812 líneas 4, 5, 8, 12 y 14: un hijo × 2.200; impuesto (1040 línea 18) de 5.000 no limita.
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 8)], agi: '50000', impuesto: '5000', ganado: '50000');

        $this->assertSame('1', $dec->linea('sch-8812', '4')->valor);
        $this->assertSame('2.200,00', $this->valor($dec, 'sch-8812', '5'));
        $this->assertSame('0', $dec->linea('sch-8812', '6')->valor);
        $this->assertSame('2.200,00', $this->valor($dec, 'sch-8812', '8'));
        $this->assertSame('200.000,00', $this->valor($dec, 'sch-8812', '9'));
        $this->assertSame('0,00', $this->valor($dec, 'sch-8812', '11'));
        $this->assertSame('5.000,00', $this->valor($dec, 'sch-8812', '13'));
        $this->assertSame('2.200,00', $this->valor($dec, 'sch-8812', '14'));
        $this->assertSame('2.200,00', $this->valor($dec, '1040', '19'));
        // Línea 16a = 12 − 14 = 0: "If zero, stop here" (p. 2).
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('sch-8812', '27')->estado);
    }

    public function test_dos_hijos_con_el_limite_del_impuesto_y_el_resto_como_credito_adicional(): void
    {
        // 12 = 4.400; hoja A: 1.500 de impuesto → 14 = 1.500. 16a = 2.900; 16b = 2 × 1.700 = 3.400; 17 = 2.900.
        // 18a = 30.000; 19 = 27.500; 20 = 27.500 × 15 % = 4.125; menos de tres hijos: 27 = min(2.900, 4.125) = 2.900.
        $dec = $this->correr(EstadoCivilIrs::Hoh, [$this->hijo(10, 4), $this->hijo(11, 9)], agi: '30000', impuesto: '1500', ganado: '30000');

        $this->assertSame('4.400,00', $this->valor($dec, 'sch-8812', '12'));
        $this->assertSame('1.500,00', $this->valor($dec, 'sch-8812', '13'));
        $this->assertSame('1.500,00', $this->valor($dec, 'sch-8812', '14'));
        $this->assertSame('2.900,00', $this->valor($dec, 'sch-8812', '16a'));
        $this->assertSame('3.400,00', $this->valor($dec, 'sch-8812', '16b'));
        $this->assertSame('2.900,00', $this->valor($dec, 'sch-8812', '17'));
        $this->assertSame('30.000,00', $this->valor($dec, 'sch-8812', '18a'));
        $this->assertSame('27.500,00', $this->valor($dec, 'sch-8812', '19'));
        $this->assertSame('4.125,00', $this->valor($dec, 'sch-8812', '20'));
        $this->assertSame('2.900,00', $this->valor($dec, 'sch-8812', '27'));
    }

    public function test_el_credito_del_2441_en_el_schedule_3_reduce_el_limite_de_la_hoja_a(): void
    {
        // Hoja A (i1040s8, p. 4): 1040 línea 18 (3.000) menos Schedule 3 línea 2 (800) = 2.200.
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 4), $this->hijo(11, 9)], agi: '40000', impuesto: '3000', ganado: '40000', sch3linea2: '800');

        $this->assertSame('2.200,00', $this->valor($dec, 'sch-8812', '13'));
        $this->assertSame('2.200,00', $this->valor($dec, 'sch-8812', '14'));
    }

    public function test_un_hijo_sin_ssn_valido_para_trabajar_cuenta_como_otro_dependiente(): void
    {
        // i1040s8, p. 1: sin SSN válido el hijo no da crédito por hijos, pero sí por otros dependientes (línea 6 × 500).
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 6, ssnValido: false)], agi: '30000', impuesto: '2000', ganado: '30000');

        $this->assertSame('0', $dec->linea('sch-8812', '4')->valor);
        $this->assertSame('1', $dec->linea('sch-8812', '6')->valor);
        $this->assertSame('500,00', $this->valor($dec, 'sch-8812', '7'));
        $this->assertSame('500,00', $this->valor($dec, 'sch-8812', '14'));
        // Sin hijos de la línea 4, la 16b es cero: no hay crédito adicional.
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('sch-8812', '27')->estado);
    }

    public function test_un_ssn_que_no_vale_para_trabajar_tampoco_da_credito_por_hijos(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 6, noValidoParaTrabajar: true)], agi: '30000', impuesto: '2000', ganado: '30000');

        $this->assertSame('0', $dec->linea('sch-8812', '4')->valor);
        $this->assertSame('1', $dec->linea('sch-8812', '6')->valor);
    }

    public function test_un_hijo_de_17_anos_al_cierre_del_ano_es_otro_dependiente(): void
    {
        // i1040s8, p. 2, ejemplo 1: el hijo que cumplió 17 en 2025 no da crédito por hijos.
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 17), $this->hijo(11, 16)], agi: '30000', impuesto: '5000', ganado: '30000');

        $this->assertSame('1', $dec->linea('sch-8812', '4')->valor);
        $this->assertSame('1', $dec->linea('sch-8812', '6')->valor);
        $this->assertSame('2.700,00', $this->valor($dec, 'sch-8812', '8'));
    }

    public function test_el_pariente_calificado_es_otro_dependiente(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(12, 70, clasificacion: ClasificacionPersona::ParienteCalificado)], agi: '30000', impuesto: '5000', ganado: '30000');

        $this->assertSame('0', $dec->linea('sch-8812', '4')->valor);
        $this->assertSame('500,00', $this->valor($dec, 'sch-8812', '14'));
    }

    public function test_quien_declara_sin_ssn_valido_no_tiene_credito_por_hijos_y_sus_hijos_cuentan_como_otros_dependientes(): void
    {
        // i1040s8, p. 1: "If you, and your spouse if filing jointly, do not have a valid SSN, you can't claim the CTC
        // or ACTC"; p. 2, ODC condición 2: la persona no se puede usar para el CTC.
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 5)], agi: '30000', impuesto: '5000', ganado: '30000', contribuyenteConSsn: false);

        $this->assertSame('0', $dec->linea('sch-8812', '4')->valor);
        $this->assertSame('1', $dec->linea('sch-8812', '6')->valor);
        $this->assertSame('500,00', $this->valor($dec, 'sch-8812', '14'));
    }

    public function test_en_conjunta_basta_con_que_un_conyuge_tenga_ssn_valido(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Mfj, [$this->hijo(10, 5)], agi: '30000', impuesto: '5000', ganado: '30000', contribuyenteConSsn: false);

        $this->assertSame('1', $dec->linea('sch-8812', '4')->valor);
    }

    public function test_reduccion_por_ingreso_en_conjunta(): void
    {
        // Línea 3 = 410.500; 410.500 − 400.000 = 10.500 → múltiplo siguiente 11.000 (línea 10); 11.000 × 5 % = 550.
        // Dos hijos: 4.400 − 550 = 3.850.
        $dec = $this->correr(EstadoCivilIrs::Mfj, [$this->hijo(10, 4), $this->hijo(11, 9)], agi: '410500', impuesto: '90000', ganado: '410500');

        $this->assertSame('400.000,00', $this->valor($dec, 'sch-8812', '9'));
        $this->assertSame('11.000,00', $this->valor($dec, 'sch-8812', '10'));
        $this->assertSame('550,00', $this->valor($dec, 'sch-8812', '11'));
        $this->assertSame('3.850,00', $this->valor($dec, 'sch-8812', '12'));
        $this->assertSame('3.850,00', $this->valor($dec, 'sch-8812', '14'));
    }

    public function test_reduccion_por_ingreso_en_soltero_con_el_ejemplo_del_formulario(): void
    {
        // Línea 10: "if the result is $1,025, enter $2,000". 201.025 − 200.000 = 1.025 → 2.000; × 5 % = 100.
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 4)], agi: '201025', impuesto: '40000', ganado: '201025');

        $this->assertSame('2.000,00', $this->valor($dec, 'sch-8812', '10'));
        $this->assertSame('100,00', $this->valor($dec, 'sch-8812', '11'));
        $this->assertSame('2.100,00', $this->valor($dec, 'sch-8812', '12'));
    }

    public function test_casado_por_separado_usa_el_umbral_de_los_demas_estados(): void
    {
        // Línea 9: "All other filing statuses—$200,000"; el 8812 no tiene otra regla para MFS.
        $dec = $this->correr(EstadoCivilIrs::Mfs, [$this->hijo(10, 4)], agi: '200425', impuesto: '40000', ganado: '200425');

        $this->assertSame('200.000,00', $this->valor($dec, 'sch-8812', '9'));
        $this->assertSame('1.000,00', $this->valor($dec, 'sch-8812', '10'));
        $this->assertSame('50,00', $this->valor($dec, 'sch-8812', '11'));
    }

    public function test_si_la_reduccion_se_come_el_credito_no_hay_ninguno(): void
    {
        // Línea 12: "No. Stop here." 1 hijo: 2.200; 244.000 − 200.000 = 44.000 → 2.200 de reducción.
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 4)], agi: '244000', impuesto: '50000', ganado: '244000');

        $this->assertSame('0,00', $this->valor($dec, 'sch-8812', '12'));
        $this->assertSame('0,00', $this->valor($dec, 'sch-8812', '14'));
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('sch-8812', '27')->estado);
    }

    public function test_credito_adicional_con_ingreso_del_trabajo_bajo_el_umbral_es_cero(): void
    {
        // 18a = 2.000, no pasa de 2.500: línea 19 en blanco, 20 = 0, 27 = min(17, 20) = 0.
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 4)], agi: '2000', impuesto: '0', ganado: '2000');

        $this->assertSame('0,00', $this->valor($dec, 'sch-8812', '14'));
        $this->assertSame('1.700,00', $this->valor($dec, 'sch-8812', '17'));
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('sch-8812', '19')->estado);
        $this->assertSame('0,00', $this->valor($dec, 'sch-8812', '20'));
        $this->assertSame('0,00', $this->valor($dec, 'sch-8812', '27'));
    }

    public function test_credito_adicional_con_ingreso_del_trabajo_sobre_el_umbral(): void
    {
        // 18a = 12.500; 19 = 10.000; 20 = 1.500; 17 = min(2.200, 1.700) = 1.700; 27 = 1.500.
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 4)], agi: '12500', impuesto: '0', ganado: '12500');

        $this->assertSame('10.000,00', $this->valor($dec, 'sch-8812', '19'));
        $this->assertSame('1.500,00', $this->valor($dec, 'sch-8812', '20'));
        $this->assertSame('1.500,00', $this->valor($dec, 'sch-8812', '27'));
    }

    public function test_el_ingreso_del_trabajo_suma_el_schedule_c_y_resta_la_mitad_del_autoempleo(): void
    {
        // Earned Income Worksheet (i1040s8, p. 8): 1z (5.000) + Schedule C 31 (10.000) − Schedule 1 15 (706,48) = 14.293,52.
        $dec = $this->declaracion(agi: '14000', impuesto: '0', ganado: '5000');
        $this->poner($dec, 'sch-c', '31', '10000');
        $this->poner($dec, 'sch-1', '15', '706.48');
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)], datos: ['1099-nec:1' => new DatoEntrada(Dinero::deDolares(10000), OrigenTraza::Leido, '1099-nec#3:1')]);
        (new Schedule8812)->calcular($e, $dec);

        $this->assertSame('14.293,52', $this->valor($dec, 'sch-8812', '18a'));
    }

    public function test_con_negocio_y_sin_la_linea_31_del_schedule_c_falta_el_dato(): void
    {
        $dec = $this->declaracion(agi: '14000', impuesto: '0', ganado: '5000');
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)], datos: ['1099-nec:1' => new DatoEntrada(Dinero::deDolares(10000), OrigenTraza::Leido, '1099-nec#3:1')]);
        (new Schedule8812)->calcular($e, $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-8812', '27')->estado);
        $this->assertSame('Falta la línea 31 del Schedule C.', $dec->linea('sch-8812', '27')->motivo);
    }

    public function test_tres_hijos_con_poco_ingreso_usan_la_parte_ii_b(): void
    {
        // 3 hijos: 12 = 6.600; 14 = 0; 16a = 6.600; 16b = 5.100; 17 = 5.100. 18a = 3.000; 19 = 500; 20 = 75 < 17.
        // Parte II-B: 21 = W-2 c. 4 (186) + c. 6 (43,50) = 229,50; 22 = 0; 24 = 27a (0) + Sch 3 l. 11 (0); 25 = 229,50;
        // 26 = max(75, 229,50) = 229,50; 27 = min(5.100, 229,50) = 229,50.
        $datos = [
            'w2:4' => new DatoEntrada(Dinero::deDolares(186), OrigenTraza::Leido, 'w2#1:4'),
            'w2:6' => new DatoEntrada(Dinero::deDolares('43.50'), OrigenTraza::Leido, 'w2#1:6'),
        ];
        $dec = $this->declaracion(agi: '3000', impuesto: '0', ganado: '3000');
        (new Schedule8812)->calcular($this->entradas(EstadoCivilIrs::Hoh, [$this->hijo(10, 2), $this->hijo(11, 5), $this->hijo(12, 8)], datos: $datos), $dec);

        $this->assertSame('5.100,00', $this->valor($dec, 'sch-8812', '16b'));
        $this->assertSame('75,00', $this->valor($dec, 'sch-8812', '20'));
        $this->assertSame('229,50', $this->valor($dec, 'sch-8812', '21'));
        $this->assertSame('229,50', $this->valor($dec, 'sch-8812', '25'));
        $this->assertSame('229,50', $this->valor($dec, 'sch-8812', '26'));
        $this->assertSame('229,50', $this->valor($dec, 'sch-8812', '27'));
    }

    public function test_tres_hijos_con_impuesto_adicional_de_medicare_dejan_la_parte_ii_b_al_preparador(): void
    {
        $dec = $this->declaracion(agi: '3000', impuesto: '0', ganado: '3000');
        $this->poner($dec, 'sch-2', '11', '9');
        (new Schedule8812)->calcular($this->entradas(EstadoCivilIrs::Hoh, [$this->hijo(10, 2), $this->hijo(11, 5), $this->hijo(12, 8)]), $dec);

        $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('sch-8812', '27')->estado);
    }

    public function test_sin_dependientes_no_aplica_y_la_linea_19_tampoco(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, [], agi: '30000', impuesto: '2000', ganado: '30000');

        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('sch-8812', '14')->estado);
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('sch-8812', '27')->estado);
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('1040', '19')->estado);
    }

    public function test_sin_estado_civil_falta_el_dato(): void
    {
        $dec = $this->correr(null, [$this->hijo(10, 4)], agi: '30000', impuesto: '2000', ganado: '30000');

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '19')->estado);
        $this->assertSame('Falta decidir el estado civil en Revisión.', $dec->linea('sch-8812', '14')->motivo);
    }

    public function test_sin_la_edad_del_hijo_falta_el_dato(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, null)], agi: '30000', impuesto: '2000', ganado: '30000');

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-8812', '14')->estado);
        $this->assertStringContainsString('persona#10', $dec->linea('sch-8812', '14')->motivo);
    }

    public function test_sin_la_linea_18_del_1040_falta_el_dato(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, '1040', '11a', '30000');
        (new Schedule8812)->calcular($this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)]), $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '19')->estado);
        $this->assertSame('Falta la línea 18 del Form 1040.', $dec->linea('sch-8812', '14')->motivo);
    }

    public function test_un_parametro_pendiente_es_falta_dato_con_su_nombre(): void
    {
        ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'credito_ctc')->where('clave', 'monto_por_dependiente')->update(['valor' => null]);

        $dec = $this->correr(EstadoCivilIrs::Single, [$this->hijo(10, 4)], agi: '30000', impuesto: '2000', ganado: '30000');

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-8812', '14')->estado);
        $this->assertStringContainsString('credito_ctc.monto_por_dependiente', $dec->linea('sch-8812', '14')->motivo);
    }

    public function test_los_datos_sin_validar_marcan_la_linea_19(): void
    {
        $dec = $this->declaracion(agi: '30000', impuesto: '5000', ganado: '30000', sinValidar: ['w2#4:1']);
        (new Schedule8812)->calcular($this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)]), $dec);

        $this->assertTrue($dec->linea('1040', '19')->usaSinValidar);
        $this->assertContains('w2#4:1', $dec->linea('1040', '19')->datosSinValidar);
    }

    public function test_la_traza_solo_lleva_referencias(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Hoh, [$this->hijo(10, 4), $this->hijo(11, 18)], agi: '30000', impuesto: '1500', ganado: '30000');

        foreach ($dec->lineas('sch-8812') as $linea) {
            foreach ($linea->traza->pasos as $paso) {
                $this->assertDoesNotMatchRegularExpression('/\d{3}-?\d{2}-?\d{4}/', $paso->texto);
                $this->assertDoesNotMatchRegularExpression('/\d{3}-?\d{2}-?\d{4}/', (string) $paso->referencia);
            }
        }
    }

    // ── Ayudantes ──

    /** @param list<PersonaEntrada> $dependientes */
    private function correr(?EstadoCivilIrs $estado, array $dependientes, string $agi, string $impuesto, string $ganado, string $sch3linea2 = '0', bool $contribuyenteConSsn = true): Declaracion
    {
        $dec = $this->declaracion($agi, $impuesto, $ganado, $sch3linea2);
        (new Schedule8812)->calcular($this->entradas($estado, $dependientes, contribuyenteConSsn: $contribuyenteConSsn), $dec);

        return $dec;
    }

    /** @param list<string> $sinValidar */
    private function declaracion(string $agi, string $impuesto, string $ganado, string $sch3linea2 = '0', array $sinValidar = []): Declaracion
    {
        $dec = new Declaracion(2025, 'prueba');
        $dec->poner(Linea::calculada('1040', '11a', Dinero::deDolares($agi), Traza::vacia(), $sinValidar));
        $this->poner($dec, '1040', '18', $impuesto);
        $this->poner($dec, '1040', '1z', $ganado);
        $this->poner($dec, '1040', '27a', '0');
        $this->poner($dec, 'sch-3', '2', $sch3linea2);
        $this->poner($dec, 'sch-3', '11', '0');

        return $dec;
    }

    /**
     * @param  list<PersonaEntrada>  $dependientes
     * @param  array<string, DatoEntrada>  $datos
     */
    private function entradas(?EstadoCivilIrs $estado, array $dependientes, array $datos = [], bool $contribuyenteConSsn = true): EntradasDeclaracion
    {
        $personas = [new PersonaEntrada(1, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, 35, $contribuyenteConSsn, false)];

        if ($estado === EstadoCivilIrs::Mfj) {
            $personas[] = new PersonaEntrada(2, RolPersona::Conyuge, ClasificacionPersona::NoDependiente, 34, true, false);
        }

        return new EntradasDeclaracion(2025, $estado, personas: [...$personas, ...$dependientes], datos: $datos);
    }

    private function hijo(int $id, ?int $edad, bool $ssnValido = true, bool $noValidoParaTrabajar = false, ClasificacionPersona $clasificacion = ClasificacionPersona::HijoCalificado): PersonaEntrada
    {
        return new PersonaEntrada($id, RolPersona::Dependiente, $clasificacion, $edad, $ssnValido, $noValidoParaTrabajar);
    }

    private function poner(Declaracion $dec, string $formulario, string $linea, string $dolares): void
    {
        $dec->poner(Linea::calculada($formulario, $linea, Dinero::deDolares($dolares), Traza::vacia()));
    }

    private function valor(Declaracion $dec, string $formulario, string $linea): string
    {
        $l = $dec->linea($formulario, $linea);
        $this->assertNotNull($l, "{$formulario}:{$linea} no se escribió");
        $this->assertSame(EstadoLinea::Calculada, $l->estado, "{$formulario}:{$linea}: {$l->motivo}");

        return $l->valor->texto();
    }
}
