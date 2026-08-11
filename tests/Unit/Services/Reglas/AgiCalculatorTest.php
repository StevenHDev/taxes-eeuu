<?php

namespace Tests\Unit\Services\Reglas;

use App\Services\Reglas\AgiCalculator;
use PHPUnit\Framework\TestCase;

class AgiCalculatorTest extends TestCase
{
    public function test_suma_basica(): void
    {
        $resultado = (new AgiCalculator)->calcular([
            'salarios' => 50000,
            'intereses_dividendos' => 500,
            'ganancias_capital' => 1000,
            'ingresos_jubilacion' => 0,
            'otros_ingresos' => 0,
            'ajustes_ingreso' => 2000,
        ]);

        $this->assertSame(51500.0, $resultado['ingreso_bruto_total']);
        $this->assertSame(2000.0, $resultado['ajustes']);
        $this->assertSame(49500.0, $resultado['agi']);
    }

    public function test_subcampo_faltante_se_trata_como_cero(): void
    {
        $resultado = (new AgiCalculator)->calcular(['salarios' => 10000]);

        $this->assertSame(10000.0, $resultado['ingreso_bruto_total']);
        $this->assertSame(0.0, $resultado['ajustes']);
        $this->assertSame(10000.0, $resultado['agi']);
    }

    public function test_agi_negativo_permitido_no_se_fuerza_a_cero(): void
    {
        $resultado = (new AgiCalculator)->calcular([
            'salarios' => 1000,
            'ganancias_capital' => -20000,
            'ajustes_ingreso' => 0,
        ]);

        $this->assertSame(-19000.0, $resultado['agi']);
    }

    /**
     * Fase 6, encontrado en pruebas end-to-end: el ingreso neto de negocio
     * (schedule_c), alquiler (schedule_e) y granja (schedule_f) se calculaba
     * para SE tax/QBI/NIIT pero nunca llegaba al AGI — Schedule 1 líneas
     * 3/5/6 → 1040 línea 8, antes de la Fase 6 ese ingreso desaparecía.
     */
    public function test_ingreso_de_negocio_alquiler_o_granja_se_suma_al_bruto(): void
    {
        $resultado = (new AgiCalculator)->calcular(
            ['salarios' => 50000, 'ajustes_ingreso' => 0],
            ajustesAdicionales: 0.0,
            ingresoNegocioRentaGranja: 15000.0,
        );

        $this->assertSame(65000.0, $resultado['ingreso_bruto_total']);
        $this->assertSame(65000.0, $resultado['agi']);
    }

    public function test_una_perdida_de_negocio_reduce_el_agi(): void
    {
        $resultado = (new AgiCalculator)->calcular(
            ['salarios' => 50000, 'ajustes_ingreso' => 0],
            ajustesAdicionales: 0.0,
            ingresoNegocioRentaGranja: -8000.0,
        );

        $this->assertSame(42000.0, $resultado['agi']);
    }
}
