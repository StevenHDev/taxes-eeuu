<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Services\Calculo\Formularios\Impuesto\HojaImpuesto;
use App\Services\Calculo\Formularios\Impuesto\TablaImpuesto;
use App\Services\Calculo\Nucleo\Dinero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * 6.4e: Tax Table y Tax Computation Worksheet de la Publication 1040 (2025).
 *
 * Todos los valores esperados salen del PDF fijado (tests/Fixtures/irs/2025/pdf/p1040.pdf), con su
 * página; ninguno sale del código que se prueba.
 */
class TablaImpuestoTest extends TestCase
{
    use RefreshDatabase;

    private function d(string $dolares): Dinero
    {
        return Dinero::deDolares($dolares);
    }

    public function test_las_franjas_son_las_del_pdf(): void
    {
        // Pub. 1040 (2025), p. 2: 0-5, 5-15, 15-25, 25-50 ... ; p. 3: 2.975-3.000 y 3.000-3.050; p. 13: 99.950-100.000.
        $casos = [
            ['0', '0', '5'],
            ['4.99', '0', '5'],
            ['5', '5', '15'],
            ['14.99', '5', '15'],
            ['15', '15', '25'],
            ['24.99', '15', '25'],
            ['25', '25', '50'],
            ['49.99', '25', '50'],
            ['50', '50', '75'],
            ['1000', '1000', '1025'],
            ['2999.99', '2975', '3000'],
            ['3000', '3000', '3050'],
            ['3049.99', '3000', '3050'],
            ['3050', '3050', '3100'],
            ['99999.99', '99950', '100000'],
        ];

        foreach ($casos as [$ingreso, $desde, $hasta]) {
            $franja = TablaImpuesto::franja($this->d($ingreso));

            $this->assertSame($this->d($desde)->centavos(), $franja['desde']->centavos(), "desde de {$ingreso}");
            $this->assertSame($this->d($hasta)->centavos(), $franja['hasta']->centavos(), "hasta de {$ingreso}");
        }
    }

    public function test_el_ejemplo_del_irs_mfj_25300_da_2562(): void
    {
        // Pub. 1040 (2025), p. 2, "Sample Table": casados declarando juntos, ingreso gravable 25.300 -> 2.562.
        $this->assertSame(256200, TablaImpuesto::paraFila('mfj', $this->d('25300'))->centavos());
    }

    public function test_las_filas_vecinas_del_ejemplo_del_irs_en_todas_las_columnas(): void
    {
        // Pub. 1040 (2025), p. 2, "Sample Table": soltero, MFJ, MFS, cabeza de familia.
        $filas = [
            ['25200', [2789, 2550, 2789, 2687]],
            ['25250', [2795, 2556, 2795, 2693]],
            ['25300', [2801, 2562, 2801, 2699]],
            ['25350', [2807, 2568, 2807, 2705]],
        ];

        foreach ($filas as [$desde, $esperado]) {
            foreach (['single', 'mfj', 'mfs', 'hoh'] as $i => $estado) {
                $this->assertSame($esperado[$i] * 100, TablaImpuesto::paraFila($estado, $this->d($desde))->centavos(), "{$estado} desde {$desde}");
            }
        }
    }

    public function test_filas_de_varias_paginas_del_pdf(): void
    {
        // [ingreso gravable (el "At least"), soltero, MFJ, MFS, HOH] y la página de la Pub. 1040 (2025).
        $filas = [
            ['0', [0, 0, 0, 0]],                          // p. 2
            ['5', [1, 1, 1, 1]],                          // p. 2
            ['15', [2, 2, 2, 2]],                         // p. 2
            ['25', [4, 4, 4, 4]],                         // p. 2
            ['75', [9, 9, 9, 9]],                         // p. 2
            ['13000', [1325, 1303, 1325, 1303]],          // p. 4
            ['35000', [3965, 3726, 3965, 3863]],          // p. 6
            ['50000', [5920, 5526, 5920, 5663]],          // p. 8
            ['75000', [11420, 8526, 11420, 9681]],        // p. 11
            ['99900', [16898, 11812, 16898, 15159]],      // p. 13
            ['99950', [16909, 11823, 16909, 15170]],      // p. 13
        ];

        foreach ($filas as [$desde, $esperado]) {
            foreach (['single', 'mfj', 'mfs', 'hoh'] as $i => $estado) {
                $this->assertSame($esperado[$i] * 100, TablaImpuesto::paraFila($estado, $this->d($desde))->centavos(), "{$estado} desde {$desde}");
            }
        }
    }

    public function test_el_borde_entre_franjas_es_cerrado_abajo_y_abierto_arriba(): void
    {
        // Pub. 1040 (2025), p. 2: la fila 25.250-25.300 da 2.556 (MFJ) y la 25.300-25.350 da 2.562.
        $this->assertSame(255600, TablaImpuesto::paraFila('mfj', $this->d('25299.99'))->centavos());
        $this->assertSame(256200, TablaImpuesto::paraFila('mfj', $this->d('25300'))->centavos());
        $this->assertSame(256200, TablaImpuesto::paraFila('mfj', $this->d('25349.99'))->centavos());
        $this->assertSame(256800, TablaImpuesto::paraFila('mfj', $this->d('25350'))->centavos());
    }

    public function test_el_ingreso_con_centavos_cae_en_la_franja_del_dolar_entero(): void
    {
        // p. 8: la fila 50.000-50.050 da 5.920 (soltero); 50.049,99 sigue en ella.
        $this->assertSame(592000, TablaImpuesto::paraFila('single', $this->d('50049.99'))->centavos());
    }

    public function test_el_minimo_es_cero(): void
    {
        // p. 2: la fila 0-5 da 0 en las cuatro columnas.
        $this->assertTrue(TablaImpuesto::paraFila('single', $this->d('0'))->esCero());
        $this->assertTrue(TablaImpuesto::paraFila('single', $this->d('4.99'))->esCero());
    }

    public function test_mfs_y_soltero_dan_lo_mismo_y_qss_usa_la_columna_de_mfj(): void
    {
        // p. 2: 25.300 -> 2.801 soltero, 2.562 MFJ, 2.801 MFS, 2.699 HOH. QSS usa la de MFJ (Pub. 1040, encabezado "Married filing jointly *").
        $this->assertSame(280100, TablaImpuesto::paraFila('mfs', $this->d('25300'))->centavos());
        $this->assertSame(TablaImpuesto::paraFila('single', $this->d('25300'))->centavos(), TablaImpuesto::paraFila('mfs', $this->d('25300'))->centavos());
        $this->assertSame(256200, TablaImpuesto::paraFila('qss', $this->d('25300'))->centavos());
        $this->assertSame(552600, TablaImpuesto::paraFila('qss', $this->d('50000'))->centavos());
    }

    public function test_el_impuesto_no_baja_cuando_sube_el_ingreso_gravable(): void
    {
        foreach (['single', 'mfj', 'mfs', 'hoh'] as $estado) {
            $anterior = -1;

            for ($ingreso = 0; $ingreso < 100000; $ingreso += 37) {
                $impuesto = TablaImpuesto::paraFila($estado, $this->d((string) $ingreso))->centavos();
                $this->assertGreaterThanOrEqual($anterior, $impuesto, "{$estado} en {$ingreso}");
                $anterior = $impuesto;
            }
        }
    }

    public function test_desde_cien_mil_no_hay_fila_en_la_tabla(): void
    {
        // Pub. 1040 (2025), p. 13: la tabla termina en 99.950-100.000 y dice "$100,000 or over: use the Tax Computation Worksheet".
        $this->assertTrue(TablaImpuesto::aplica($this->d('99999.99')));
        $this->assertFalse(TablaImpuesto::aplica($this->d('100000')));

        $this->expectException(InvalidArgumentException::class);
        TablaImpuesto::paraFila('single', $this->d('100000'));
    }

    public function test_un_ingreso_negativo_no_tiene_fila(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TablaImpuesto::paraFila('single', $this->d('-1'));
    }

    public function test_un_estado_civil_desconocido_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TablaImpuesto::paraFila('viudo', $this->d('1000'));
    }

    /**
     * Filas de la Tax Computation Worksheet (Pub. 1040 (2025), p. 14 secciones A a C, p. 15 sección D):
     * [estado civil, ingreso desde el que aplica la fila, tasa, cifra (d)].
     *
     * @return array<string, list<array{0: string, 1: int, 2: string, 3: string}>>
     */
    private function filasDeLaHoja(): array
    {
        return [
            'single' => [ // sección A
                ['single', 100000, '0.22', '5086.00'],
                ['single', 103350, '0.24', '7153.00'],
                ['single', 197300, '0.32', '22937.00'],
                ['single', 250525, '0.35', '30452.75'],
                ['single', 626350, '0.37', '42979.75'],
            ],
            'mfj' => [ // sección B (MFJ y QSS)
                ['mfj', 100000, '0.22', '10172.00'],
                ['mfj', 206700, '0.24', '14306.00'],
                ['mfj', 394600, '0.32', '45874.00'],
                ['mfj', 501050, '0.35', '60905.50'],
                ['mfj', 751600, '0.37', '75937.50'],
            ],
            'mfs' => [ // sección C
                ['mfs', 100000, '0.22', '5086.00'],
                ['mfs', 103350, '0.24', '7153.00'],
                ['mfs', 197300, '0.32', '22937.00'],
                ['mfs', 250525, '0.35', '30452.75'],
                ['mfs', 375800, '0.37', '37968.75'],
            ],
            'hoh' => [ // sección D (p. 15)
                ['hoh', 100000, '0.22', '6825.00'],
                ['hoh', 103350, '0.24', '8892.00'],
                ['hoh', 197300, '0.32', '24676.00'],
                ['hoh', 250500, '0.35', '32191.00'],
                ['hoh', 626350, '0.37', '44718.00'],
            ],
        ];
    }

    public function test_la_hoja_usa_la_tasa_y_la_cifra_del_pdf_en_cada_fila(): void
    {
        foreach ($this->filasDeLaHoja() as $filas) {
            foreach ($filas as [$estado, $desde, $tasa, $resta]) {
                // Un dólar por encima del "desde" siempre cae dentro de la fila (la 1.ª empieza en 100.000, "at least").
                $resultado = HojaImpuesto::calcular($estado, $this->d((string) ($desde + 1)));

                $this->assertSame($tasa, $resultado['tasa'], "tasa de {$estado} desde {$desde}");
                $this->assertSame($this->d($resta)->centavos(), $resultado['resta']->centavos(), "cifra (d) de {$estado} desde {$desde}");
            }
        }
    }

    public function test_la_hoja_en_los_cortes_de_cada_tramo(): void
    {
        // Calculado a mano con la hoja: ingreso x tasa - cifra (d). Cortes y un dólar después, soltero (sección A).
        $casos = [
            ['single', '100000', '16914.00'],
            ['single', '100001', '16914.22'],
            ['single', '103350', '17651.00'],
            ['single', '103351', '17651.24'],
            ['single', '197300', '40199.00'],
            ['single', '197301', '40199.32'],
            ['single', '250525', '57231.00'],
            ['single', '250526', '57231.35'],
            ['single', '626350', '188769.75'],
            ['single', '626351', '188770.12'],
            // MFJ y QSS (sección B).
            ['mfj', '100000', '11828.00'],
            ['mfj', '206700', '35302.00'],
            ['mfj', '206701', '35302.24'],
            ['mfj', '394600', '80398.00'],
            ['mfj', '394601', '80398.32'],
            ['mfj', '501050', '114462.00'],
            ['mfj', '501051', '114462.35'],
            ['mfj', '751600', '202154.50'],
            ['mfj', '751601', '202154.87'],
            ['qss', '394601', '80398.32'],
            // MFS (sección C): el 37 % empieza en 375.800.
            ['mfs', '100000', '16914.00'],
            ['mfs', '250525', '57231.00'],
            ['mfs', '250526', '57231.35'],
            ['mfs', '375800', '101077.25'],
            ['mfs', '375801', '101077.62'],
            // Cabeza de familia (sección D, p. 15): el 35 % empieza en 250.500.
            ['hoh', '100000', '15175.00'],
            ['hoh', '103351', '15912.24'],
            ['hoh', '197301', '38460.32'],
            ['hoh', '250500', '55484.00'],
            ['hoh', '250501', '55484.35'],
            ['hoh', '626350', '187031.50'],
            ['hoh', '626351', '187031.87'],
        ];

        foreach ($casos as [$estado, $ingreso, $esperado]) {
            $this->assertSame($this->d($esperado)->centavos(), HojaImpuesto::calcular($estado, $this->d($ingreso))['impuesto']->centavos(), "{$estado} {$ingreso}");
        }
    }

    public function test_la_hoja_es_continua_en_cada_corte_de_tramo(): void
    {
        // En cada corte, la fila de arriba y la de abajo del PDF dan el mismo impuesto: ingreso x tasa - (d).
        foreach ($this->filasDeLaHoja() as $filas) {
            for ($i = 1; $i < count($filas); $i++) {
                [$estado, $corte] = $filas[$i];
                $abajo = $this->d((string) $corte)->por($filas[$i - 1][2])->menos($this->d($filas[$i - 1][3]));
                $arriba = $this->d((string) $corte)->por($filas[$i][2])->menos($this->d($filas[$i][3]));

                $this->assertSame($abajo->centavos(), $arriba->centavos(), "continuidad de {$estado} en {$corte}");
                $this->assertSame($abajo->centavos(), HojaImpuesto::calcular($estado, $this->d((string) $corte))['impuesto']->centavos());
            }
        }
    }

    public function test_el_paso_de_la_tabla_a_la_hoja_en_cien_mil(): void
    {
        // Tabla (p. 13): 99.950-100.000 da 16.909 soltero; la hoja en 100.000 da 16.914 (0,22 x 100.000 - 5.086).
        $this->assertSame(1690900, TablaImpuesto::paraFila('single', $this->d('99999.99'))->centavos());
        $this->assertSame(1691400, HojaImpuesto::calcular('single', $this->d('100000'))['impuesto']->centavos());
    }

    public function test_la_hoja_no_aplica_con_menos_de_cien_mil(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HojaImpuesto::calcular('single', $this->d('99999.99'));
    }
}
