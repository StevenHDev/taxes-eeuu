<?php

namespace Tests\Unit\Services\Reglas;

use App\Enums\FilingStatus;
use App\Services\Reglas\TaxableIncomeAndTaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada monto esperado está verificado directamente contra las Tax Rate
 * Tables de Rev. Proc. 2024-40 (tablas 1-3, § 1(j)) — no recalculado a mano.
 */
class TaxableIncomeAndTaxCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingreso_gravable_no_baja_de_cero(): void
    {
        $resultado = (new TaxableIncomeAndTaxCalculator)->calcular(2025, FilingStatus::Single, 10000.0, 15750.0, 0.0);

        $this->assertSame(0.0, $resultado['ingreso_gravable']);
        $this->assertSame(0.0, $resultado['impuesto']);
    }

    public function test_single_tramo_22_por_ciento(): void
    {
        // Gravable 50,000 → tabla 3: $5,578.50 + 22% del exceso sobre 48,475.
        $resultado = (new TaxableIncomeAndTaxCalculator)->calcular(2025, FilingStatus::Single, 65750.0, 15750.0, 0.0);

        $this->assertSame(50000.0, $resultado['ingreso_gravable']);
        $this->assertEqualsWithDelta(5914.0, $resultado['impuesto'], 0.01);
    }

    public function test_mfj_tramo_22_por_ciento(): void
    {
        // Gravable 100,000 → tabla 1: $11,157 + 22% del exceso sobre 96,950.
        $resultado = (new TaxableIncomeAndTaxCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, 100000.0, 0.0, 0.0);

        $this->assertEqualsWithDelta(11828.0, $resultado['impuesto'], 0.01);
    }

    public function test_hoh_tramo_22_por_ciento(): void
    {
        // Gravable 70,000 → tabla 2: $7,442 + 22% del exceso sobre 64,850.
        $resultado = (new TaxableIncomeAndTaxCalculator)->calcular(2025, FilingStatus::HeadOfHousehold, 70000.0, 0.0, 0.0);

        $this->assertEqualsWithDelta(8575.0, $resultado['impuesto'], 0.01);
    }

    public function test_qss_usa_la_misma_tabla_que_mfj(): void
    {
        $mfj = (new TaxableIncomeAndTaxCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, 100000.0, 0.0, 0.0);
        $qss = (new TaxableIncomeAndTaxCalculator)->calcular(2025, FilingStatus::QualifyingSurvivingSpouse, 100000.0, 0.0, 0.0);

        $this->assertSame($mfj['impuesto'], $qss['impuesto']);
    }

    public function test_tramo_maximo_37_por_ciento(): void
    {
        // Gravable 700,000 (soltero) → tabla 3: $188,769.75 + 37% del exceso
        // sobre 626,350 (73,650 × 0.37 = 27,250.50).
        $resultado = (new TaxableIncomeAndTaxCalculator)->calcular(2025, FilingStatus::Single, 700000.0, 0.0, 0.0);

        $this->assertEqualsWithDelta(216020.25, $resultado['impuesto'], 0.01);
    }

    public function test_la_deduccion_qbi_reduce_el_ingreso_gravable(): void
    {
        $resultado = (new TaxableIncomeAndTaxCalculator)->calcular(2025, FilingStatus::Single, 100000.0, 15750.0, 10000.0);

        $this->assertSame(74250.0, $resultado['ingreso_gravable']);
    }
}
