<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\ImpuestoYSchedule2;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 6.4e: ImpuestoYSchedule2 (1040 líneas 16 a 24 y Schedule 2 líneas 3, 4, 11 y 21).
 *
 * Los valores esperados salen de la Tax Table y la Tax Computation Worksheet de la Pub. 1040 (2025) y de
 * las líneas del Form 8959 (2025, p. 1), calculados a mano; ninguno sale del código que se prueba.
 */
class ImpuestoYSchedule2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Los motivos se comparan en español; el idioma por defecto de la aplicación es inglés.
        app()->setLocale('es');
    }

    private function d(string $dolares): Dinero
    {
        return Dinero::deDolares($dolares);
    }

    private function poner(Declaracion $dec, string $formulario, string $linea, string $dolares): void
    {
        $dec->poner(Linea::calculada($formulario, $linea, $this->d($dolares), Traza::vacia()));
    }

    /**
     * La declaración con lo que escriben las otras calculadoras: ingreso gravable, SE (líneas 6 y 12) y 19/20.
     *
     * @param  array<string, DatoEntrada>  $datos
     * @return array{0: EntradasDeclaracion, 1: Declaracion}
     */
    private function armar(?EstadoCivilIrs $estado, string $ingresoGravable, array $datos = [], string $se6 = '0', string $se12 = '0', string $l19 = '0', string $l20 = '0'): array
    {
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, '1040', '15', $ingresoGravable);
        $this->poner($dec, 'sch-se', '6', $se6);
        $this->poner($dec, 'sch-se', '12', $se12);
        $this->poner($dec, '1040', '19', $l19);
        $this->poner($dec, '1040', '20', $l20);

        return [new EntradasDeclaracion(2025, $estado, datos: $datos), $dec];
    }

    private function correr(EntradasDeclaracion $e, Declaracion $dec): Declaracion
    {
        (new ImpuestoYSchedule2)->calcular($e, $dec);

        return $dec;
    }

    private function valor(Declaracion $dec, string $formulario, string $linea): string
    {
        $l = $dec->linea($formulario, $linea);
        $this->assertNotNull($l, "{$formulario}:{$linea} no se escribió");
        $this->assertSame(EstadoLinea::Calculada, $l->estado, "{$formulario}:{$linea}: {$l->motivo}");

        return $l->valor->texto();
    }

    private function w2Casilla5(string $dolares, bool $validado = true): array
    {
        return ['w2:5' => new DatoEntrada($this->d($dolares), OrigenTraza::Leido, 'doc-1', $validado)];
    }

    public function test_el_codigo_del_formulario_es_el_1040(): void
    {
        $this->assertSame('1040', (new ImpuestoYSchedule2)->formulario());
    }

    public function test_soltero_con_50000_usa_la_tax_table_y_llena_todas_las_lineas(): void
    {
        // Pub. 1040 (2025), p. 8: fila 50.000-50.050, soltero = 5.920.
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000');
        $this->correr($e, $dec);

        $this->assertSame('5.920,00', $this->valor($dec, '1040', '16'));
        $this->assertSame('0,00', $this->valor($dec, 'sch-2', '3'));
        $this->assertSame('0,00', $this->valor($dec, '1040', '17'));
        $this->assertSame('5.920,00', $this->valor($dec, '1040', '18'));
        $this->assertSame('0,00', $this->valor($dec, '1040', '21'));
        $this->assertSame('5.920,00', $this->valor($dec, '1040', '22'));
        $this->assertSame('0,00', $this->valor($dec, 'sch-2', '4'));
        $this->assertSame('0,00', $this->valor($dec, 'sch-2', '11'));
        $this->assertSame('0,00', $this->valor($dec, 'sch-2', '21'));
        $this->assertSame('0,00', $this->valor($dec, '1040', '23'));
        $this->assertSame('5.920,00', $this->valor($dec, '1040', '24'));
        $this->assertSame('completa', $dec->estadoGeneral());
    }

    public function test_el_ejemplo_del_irs_mfj_25300_da_2562(): void
    {
        // Pub. 1040 (2025), p. 2, ejemplo: casados declarando juntos, línea 15 = 25.300 -> línea 16 = 2.562.
        [$e, $dec] = $this->armar(EstadoCivilIrs::Mfj, '25300');
        $this->correr($e, $dec);

        $this->assertSame('2.562,00', $this->valor($dec, '1040', '16'));
        $this->assertSame('2.562,00', $this->valor($dec, '1040', '24'));
    }

    public function test_alrededor_de_cien_mil_cambia_de_la_tabla_a_la_hoja(): void
    {
        // Soltero. Tax Table p. 13: 99.950-100.000 = 16.909. Hoja, p. 14, sección A: 100.000 x 0,22 - 5.086 = 16.914;
        // 100.001 x 0,22 - 5.086 = 16.914,22.
        foreach ([['99999', '16.909,00'], ['100000', '16.914,00'], ['100001', '16.914,22']] as [$ingreso, $esperado]) {
            [$e, $dec] = $this->armar(EstadoCivilIrs::Single, $ingreso);
            $this->assertSame($esperado, $this->valor($this->correr($e, $dec), '1040', '16'), "línea 15 = {$ingreso}");
        }
    }

    public function test_la_hoja_por_estado_civil_con_cifras_del_pdf(): void
    {
        // Pub. 1040 (2025), p. 14 (A, B, C) y p. 15 (D): ingreso x tasa - cifra (d), calculado a mano.
        $casos = [
            [EstadoCivilIrs::Single, '150000', '28.847,00'],    // 0,24 x 150.000 - 7.153
            [EstadoCivilIrs::Mfj, '300000', '57.694,00'],       // 0,24 x 300.000 - 14.306
            [EstadoCivilIrs::Qss, '300000', '57.694,00'],       // QSS = sección B
            [EstadoCivilIrs::Mfs, '400000', '110.031,25'],      // 0,37 x 400.000 - 37.968,75 (el 37 % empieza en 375.800)
            [EstadoCivilIrs::Hoh, '300000', '72.809,00'],       // 0,35 x 300.000 - 32.191
            [EstadoCivilIrs::Single, '700000', '216.020,25'],   // 0,37 x 700.000 - 42.979,75
        ];

        foreach ($casos as [$estado, $ingreso, $esperado]) {
            [$e, $dec] = $this->armar($estado, $ingreso);
            $this->assertSame($esperado, $this->valor($this->correr($e, $dec), '1040', '16'), "{$estado->value} {$ingreso}");
        }
    }

    public function test_sin_estado_civil_las_lineas_que_dependen_de_el_son_falta_dato(): void
    {
        [$e, $dec] = $this->armar(null, '50000');
        $this->correr($e, $dec);

        foreach ([['1040', '16'], ['sch-2', '11']] as [$f, $l]) {
            $this->assertSame(EstadoLinea::FaltaDato, $dec->linea($f, $l)->estado);
            $this->assertSame('Falta decidir el estado civil en Revisión.', $dec->linea($f, $l)->motivo);
        }

        foreach (['18', '22', '24'] as $l) {
            $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', $l)->estado);
        }

        $this->assertSame('incompleta', $dec->estadoGeneral());
    }

    public function test_sin_la_linea_15_la_16_y_las_que_siguen_son_falta_dato(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, 'sch-se', '6', '0');
        $this->poner($dec, 'sch-se', '12', '0');
        $e = new EntradasDeclaracion(2025, EstadoCivilIrs::Single);

        $this->correr($e, $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '16')->estado);
        $this->assertSame('Falta la línea 15 del Form 1040.', $dec->linea('1040', '16')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '18')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '24')->estado);
    }

    public function test_sin_las_lineas_19_y_20_la_21_la_22_y_la_24_son_falta_dato(): void
    {
        // Las escriben 6.4f (8812 y Schedule 3): mientras no estén, no se supone cero.
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, '1040', '15', '50000');
        $this->poner($dec, 'sch-se', '6', '0');
        $this->poner($dec, 'sch-se', '12', '0');
        $e = new EntradasDeclaracion(2025, EstadoCivilIrs::Single);

        $this->correr($e, $dec);

        $this->assertSame('5.920,00', $this->valor($dec, '1040', '18'));
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '21')->estado);
        $this->assertSame('Falta la línea 19 del Form 1040.', $dec->linea('1040', '21')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '22')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '24')->estado);
        $this->assertSame('5.920,00', $this->valor($dec, '1040', '16'));
    }

    public function test_sin_la_linea_12_del_schedule_se_la_4_la_21_la_23_y_la_24_son_falta_dato(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, '1040', '15', '50000');
        $this->poner($dec, 'sch-se', '6', '0');
        $this->poner($dec, '1040', '19', '0');
        $this->poner($dec, '1040', '20', '0');
        $e = new EntradasDeclaracion(2025, EstadoCivilIrs::Single);

        $this->correr($e, $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-2', '4')->estado);
        $this->assertSame('Falta la línea 12 del Schedule SE.', $dec->linea('sch-2', '4')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-2', '21')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '23')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '24')->estado);
        $this->assertSame('0,00', $this->valor($dec, 'sch-2', '11'));
    }

    public function test_las_lineas_21_y_22_restan_los_creditos_y_no_bajan_de_cero(): void
    {
        // Form 1040 (2025), p. 2: 21 = 19 + 20; 22 = 18 - 21, "if zero or less, enter -0-".
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', l19: '2200', l20: '300');
        $this->correr($e, $dec);

        $this->assertSame('2.500,00', $this->valor($dec, '1040', '21'));
        $this->assertSame('3.420,00', $this->valor($dec, '1040', '22'));    // 5.920 - 2.500
        $this->assertSame('3.420,00', $this->valor($dec, '1040', '24'));

        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', l19: '10000');
        $this->correr($e, $dec);

        $this->assertSame('0,00', $this->valor($dec, '1040', '22'));
        $this->assertSame('0,00', $this->valor($dec, '1040', '24'));
    }

    public function test_el_impuesto_de_autoempleo_y_el_medicare_adicional_suman_en_la_21_y_van_a_la_23_y_24(): void
    {
        // Schedule 2 (2025): 21 = 4 + 11 (piloto). SE línea 12 = 7.000; Medicare adicional: salarios 250.000, soltero,
        // (250.000 - 200.000) x 0,009 = 450 (Form 8959, líneas 4 a 7).
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', $this->w2Casilla5('250000'), se12: '7000');
        $this->correr($e, $dec);

        $this->assertSame('7.000,00', $this->valor($dec, 'sch-2', '4'));
        $this->assertSame('450,00', $this->valor($dec, 'sch-2', '11'));
        $this->assertSame('7.450,00', $this->valor($dec, 'sch-2', '21'));
        $this->assertSame('7.450,00', $this->valor($dec, '1040', '23'));
        $this->assertSame('13.370,00', $this->valor($dec, '1040', '24'));   // 5.920 + 7.450
    }

    public function test_medicare_adicional_parte_i_por_estado_civil_y_en_el_umbral(): void
    {
        // Form 8959 (2025), p. 1, líneas 5 a 7: umbral 250.000 MFJ, 125.000 MFS, 200.000 los demás; tasa 0,9 %.
        $casos = [
            [EstadoCivilIrs::Single, '200000', '0,00'],
            [EstadoCivilIrs::Single, '250000', '450,00'],
            [EstadoCivilIrs::Hoh, '250000', '450,00'],
            [EstadoCivilIrs::Qss, '250000', '450,00'],
            [EstadoCivilIrs::Mfj, '250000', '0,00'],
            [EstadoCivilIrs::Mfj, '300000', '450,00'],
            [EstadoCivilIrs::Mfs, '125000', '0,00'],
            [EstadoCivilIrs::Mfs, '150000', '225,00'],
            [EstadoCivilIrs::Single, '150000', '0,00'],
        ];

        foreach ($casos as [$estado, $salarios, $esperado]) {
            [$e, $dec] = $this->armar($estado, '50000', $this->w2Casilla5($salarios));
            $this->assertSame($esperado, $this->valor($this->correr($e, $dec), 'sch-2', '11'), "{$estado->value} con salarios {$salarios}");
        }
    }

    public function test_medicare_adicional_parte_ii_sobre_el_autoempleo_con_el_umbral_reducido_por_los_salarios(): void
    {
        // Form 8959 (2025), p. 1, líneas 8 a 13. Soltero: línea 9 = 200.000; línea 11 = 9 - 10 (salarios); línea 12 = 8 - 11.
        $casos = [
            // [salarios, autoempleo (SE línea 6), esperado]
            [null, '250000', '450,00'],       // línea 11 = 200.000; línea 12 = 50.000; x 0,009
            [null, '200000', '0,00'],
            ['150000', '100000', '450,00'],   // línea 11 = 50.000; línea 12 = 50.000 -> 450 (parte I = 0)
            ['250000', '100000', '1350,00'],  // parte I 450; línea 11 = 0; línea 12 = 100.000 -> 900
            ['100000', '100000', '0,00'],     // línea 11 = 100.000; línea 12 = 0
            [null, '-5000', '0,00'],          // pérdida: línea 8 en cero
        ];

        foreach ($casos as [$salarios, $autoempleo, $esperado]) {
            [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', $salarios === null ? [] : $this->w2Casilla5($salarios), se6: $autoempleo);
            $this->assertSame(
                Dinero::deDolares(str_replace(',', '.', $esperado))->texto(),
                $this->valor($this->correr($e, $dec), 'sch-2', '11'),
                "salarios {$salarios} y autoempleo {$autoempleo}",
            );
        }
    }

    public function test_medicare_adicional_parte_ii_para_mfs_y_mfj(): void
    {
        // Form 8959 (2025), líneas 8 a 13: MFS umbral 125.000; MFJ 250.000. Autoempleo 150.000 sin salarios.
        [$e, $dec] = $this->armar(EstadoCivilIrs::Mfs, '50000', se6: '150000');
        $this->assertSame('225,00', $this->valor($this->correr($e, $dec), 'sch-2', '11'));    // (150.000 - 125.000) x 0,009

        [$e, $dec] = $this->armar(EstadoCivilIrs::Mfj, '50000', se6: '300000');
        $this->assertSame('450,00', $this->valor($this->correr($e, $dec), 'sch-2', '11'));    // (300.000 - 250.000) x 0,009
    }

    public function test_sin_la_linea_6_del_schedule_se_el_medicare_adicional_es_falta_dato(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, '1040', '15', '50000');
        $e = new EntradasDeclaracion(2025, EstadoCivilIrs::Single);

        $this->correr($e, $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-2', '11')->estado);
        $this->assertSame('Falta la línea 6 del Schedule SE.', $dec->linea('sch-2', '11')->motivo);
    }

    public function test_el_schedule_se_que_no_aplica_cuenta_como_cero(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, '1040', '15', '50000');
        $this->poner($dec, '1040', '19', '0');
        $this->poner($dec, '1040', '20', '0');
        $dec->poner(Linea::noAplica('sch-se', '6'));
        $dec->poner(Linea::noAplica('sch-se', '12'));

        $this->correr(new EntradasDeclaracion(2025, EstadoCivilIrs::Single), $dec);

        $this->assertSame('0,00', $this->valor($dec, 'sch-2', '4'));
        $this->assertSame('0,00', $this->valor($dec, 'sch-2', '11'));
        $this->assertSame('5.920,00', $this->valor($dec, '1040', '24'));
    }

    public function test_la_linea_3_del_schedule_2_toma_la_29_del_8962(): void
    {
        // Schedule 2 (2025), 1a: "Excess advance premium tax credit repayment. Attach Form 8962"; Form 8962, línea 29.
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000');
        $this->poner($dec, 'f8962', '29', '1234');
        $this->correr($e, $dec);

        $this->assertSame('1.234,00', $this->valor($dec, 'sch-2', '3'));
        $this->assertSame('1.234,00', $this->valor($dec, '1040', '17'));
        $this->assertSame('7.154,00', $this->valor($dec, '1040', '18'));    // 5.920 + 1.234
        $this->assertSame('7.154,00', $this->valor($dec, '1040', '24'));
    }

    public function test_con_1095a_y_sin_la_linea_29_del_8962_la_linea_3_es_falta_dato(): void
    {
        $datos = ['1095-a:11' => new DatoEntrada($this->d('4000'), OrigenTraza::Leido, 'doc-9')];
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', $datos);
        $this->correr($e, $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-2', '3')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '17')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '24')->estado);
    }

    public function test_el_8962_que_no_aplica_cuenta_como_cero_en_la_linea_3(): void
    {
        $datos = ['1095-a:11' => new DatoEntrada($this->d('4000'), OrigenTraza::Leido, 'doc-9')];
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', $datos);
        $dec->poner(Linea::noAplica('f8962', '29'));
        $this->correr($e, $dec);

        $this->assertSame('0,00', $this->valor($dec, 'sch-2', '3'));
    }

    public function test_dividendos_o_ganancias_de_capital_dejan_la_linea_16_no_soportada(): void
    {
        // Pub. 1040 (2025), p. 14, nota: con dividendos calificados o ganancias de capital se usa otra hoja (QDCG).
        $datos = ['1099-div:1b' => new DatoEntrada($this->d('300'), OrigenTraza::Leido, 'doc-2')];
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', $datos);
        $this->correr($e, $dec);

        $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('1040', '16')->estado);
        $this->assertStringContainsString('1099-div', $dec->linea('1040', '16')->motivo);
        $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('1040', '18')->estado);
        $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('1040', '24')->estado);
        $this->assertSame('parcial', $dec->estadoGeneral());
    }

    public function test_intereses_o_alquileres_dejan_el_8960_no_soportado_y_arrastran_la_21_23_y_24(): void
    {
        foreach (['1099-int:1', 'registros-alquiler:total'] as $clave) {
            $datos = [$clave => new DatoEntrada($this->d('500'), OrigenTraza::Leido, 'doc-3')];
            [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', $datos);
            $this->correr($e, $dec);

            $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('sch-2', '12')->estado, $clave);
            $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('sch-2', '21')->estado);
            $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('1040', '23')->estado);
            $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('1040', '24')->estado);
            // El impuesto de la línea 16 sí se calcula: los intereses no cambian la tabla.
            $this->assertSame('5.920,00', $this->valor($dec, '1040', '16'));
        }

        $this->assertSame('parcial', $dec->estadoGeneral());
    }

    public function test_sin_datos_que_disparen_el_8960_la_linea_12_no_se_escribe(): void
    {
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000');
        $this->correr($e, $dec);

        $this->assertNull($dec->linea('sch-2', '12'));
    }

    public function test_un_dato_sin_validar_marca_la_linea_y_las_que_dependen_de_ella(): void
    {
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000', $this->w2Casilla5('250000', validado: false), se12: '7000');
        $this->correr($e, $dec);

        $this->assertTrue($dec->linea('sch-2', '11')->usaSinValidar);
        $this->assertSame(['doc-1'], $dec->linea('sch-2', '11')->datosSinValidar);
        $this->assertTrue($dec->linea('sch-2', '21')->usaSinValidar);
        $this->assertTrue($dec->linea('1040', '23')->usaSinValidar);
        $this->assertTrue($dec->linea('1040', '24')->usaSinValidar);
        $this->assertSame(['doc-1'], $dec->linea('1040', '24')->datosSinValidar);
        $this->assertFalse($dec->linea('1040', '16')->usaSinValidar);
    }

    public function test_una_linea_de_entrada_sin_validar_se_propaga_a_la_16(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $dec->poner(Linea::calculada('1040', '15', $this->d('50000'), Traza::vacia(), ['doc-7']));
        $this->poner($dec, 'sch-se', '6', '0');
        $this->poner($dec, 'sch-se', '12', '0');
        $this->poner($dec, '1040', '19', '0');
        $this->poner($dec, '1040', '20', '0');

        $this->correr(new EntradasDeclaracion(2025, EstadoCivilIrs::Single), $dec);

        $this->assertTrue($dec->linea('1040', '16')->usaSinValidar);
        $this->assertSame(['doc-7'], $dec->linea('1040', '16')->datosSinValidar);
        $this->assertSame(['doc-7'], $dec->linea('1040', '24')->datosSinValidar);
    }

    public function test_un_parametro_pendiente_deja_la_linea_en_falta_dato_con_el_motivo(): void
    {
        ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'tax_brackets')->where('clave', 'single')->delete();
        ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'additional_medicare_tax')->where('clave', 'tasa')->delete();
        ParametrosFiscales::invalidate();

        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000');
        $this->correr($e, $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '16')->estado);
        $this->assertSame('No se puede calcular: falta el parámetro tax_brackets.single de 2025 (Catálogo → Parámetros).', $dec->linea('1040', '16')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-2', '11')->estado);
        $this->assertSame('No se puede calcular: falta el parámetro additional_medicare_tax.tasa de 2025 (Catálogo → Parámetros).', $dec->linea('sch-2', '11')->motivo);
    }

    public function test_la_traza_de_la_linea_16_lleva_entradas_parametro_operacion_y_resultado_sin_valores_sensibles(): void
    {
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '50000');
        $this->correr($e, $dec);

        $pasos = $dec->linea('1040', '16')->traza->pasos;
        $tipos = array_map(fn ($p) => $p->tipo, $pasos);

        $this->assertContains(TipoPaso::Entrada, $tipos);
        $this->assertContains(TipoPaso::Parametro, $tipos);
        $this->assertContains(TipoPaso::Operacion, $tipos);
        $this->assertSame(TipoPaso::Resultado, end($tipos));

        $referencias = array_filter(array_map(fn ($p) => $p->referencia, $pasos));
        $this->assertContains('1040:15', $referencias);
        $this->assertContains('tax_brackets.single', $referencias);
        $this->assertContains('estado_civil_irs', $referencias);
    }

    public function test_la_hoja_deja_el_origen_de_la_linea_15_como_linea(): void
    {
        [$e, $dec] = $this->armar(EstadoCivilIrs::Mfj, '300000');
        $this->correr($e, $dec);

        $entrada = $dec->linea('1040', '16')->traza->pasos[0];

        $this->assertSame(OrigenTraza::Linea, $entrada->origen);
        $this->assertSame('1040:15', $entrada->referencia);
    }

    public function test_un_ingreso_gravable_cero_da_impuesto_cero(): void
    {
        // Pub. 1040 (2025), p. 2: la fila 0-5 da 0.
        [$e, $dec] = $this->armar(EstadoCivilIrs::Single, '0');
        $this->correr($e, $dec);

        $this->assertSame('0,00', $this->valor($dec, '1040', '16'));
        $this->assertSame('0,00', $this->valor($dec, '1040', '24'));
    }
}
