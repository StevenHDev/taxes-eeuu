<?php

namespace Tests\Unit\Services\Reglas;

use App\Services\Reglas\SettlementCalculator;
use Tests\TestCase;

class SettlementCalculatorTest extends TestCase
{
    public function test_reembolso_cuando_los_pagos_superan_el_impuesto(): void
    {
        $resultado = (new SettlementCalculator)->calcular(5000.0, 1000.0, 0.0, 0.0, 0.0, 6000.0);

        $this->assertSame(4000.0, $resultado['total_impuesto']);
        $this->assertSame(2000.0, $resultado['reembolso']);
        $this->assertSame(0.0, $resultado['saldo_a_pagar']);
    }

    public function test_saldo_a_pagar_cuando_los_pagos_no_alcanzan(): void
    {
        $resultado = (new SettlementCalculator)->calcular(10000.0, 0.0, 2000.0, 0.0, 0.0, 5000.0);

        $this->assertSame(12000.0, $resultado['total_impuesto']);
        $this->assertSame(0.0, $resultado['reembolso']);
        $this->assertSame(7000.0, $resultado['saldo_a_pagar']);
    }

    public function test_los_creditos_nunca_dejan_el_impuesto_antes_de_otros_en_negativo(): void
    {
        $resultado = (new SettlementCalculator)->calcular(1000.0, 5000.0, 0.0, 0.0, 0.0, 0.0);

        $this->assertSame(0.0, $resultado['impuesto_antes_otros']);
    }

    public function test_otros_impuestos_no_se_reducen_por_creditos(): void
    {
        // SE tax, NIIT y Additional Medicare Tax se agregan DESPUÉS de que
        // los créditos ya redujeron el impuesto sobre el ingreso — nunca los
        // reducen los créditos no reembolsables.
        $resultado = (new SettlementCalculator)->calcular(1000.0, 1000.0, 3000.0, 0.0, 0.0, 0.0);

        $this->assertSame(0.0, $resultado['impuesto_antes_otros']);
        $this->assertSame(3000.0, $resultado['otros_impuestos']);
        $this->assertSame(3000.0, $resultado['total_impuesto']);
    }
}
