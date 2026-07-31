<?php

namespace Tests\Unit\Services\Reglas;

use App\Services\Reglas\AgiCalculator;
use PHPUnit\Framework\TestCase;

class AgiCalculatorTest extends TestCase
{
    public function test_suma_basica(): void
    {
        $resultado = (new AgiCalculator())->calcular([
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
        $resultado = (new AgiCalculator())->calcular(['salarios' => 10000]);

        $this->assertSame(10000.0, $resultado['ingreso_bruto_total']);
        $this->assertSame(0.0, $resultado['ajustes']);
        $this->assertSame(10000.0, $resultado['agi']);
    }

    public function test_agi_negativo_permitido_no_se_fuerza_a_cero(): void
    {
        $resultado = (new AgiCalculator())->calcular([
            'salarios' => 1000,
            'ganancias_capital' => -20000,
            'ajustes_ingreso' => 0,
        ]);

        $this->assertSame(-19000.0, $resultado['agi']);
    }
}
