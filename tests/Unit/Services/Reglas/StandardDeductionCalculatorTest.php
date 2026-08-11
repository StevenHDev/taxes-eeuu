<?php

namespace Tests\Unit\Services\Reglas;

use App\Enums\FilingStatus;
use App\Services\Reglas\StandardDeductionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandardDeductionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_usa_la_estandar_cuando_la_itemizada_es_menor(): void
    {
        $resultado = (new StandardDeductionCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, 20000.0);

        $this->assertFalse($resultado['usa_itemizada']);
        $this->assertSame(31500.0, $resultado['deduccion_aplicable']);
    }

    public function test_usa_la_itemizada_cuando_es_mayor(): void
    {
        $resultado = (new StandardDeductionCalculator)->calcular(2025, FilingStatus::Single, 20000.0);

        $this->assertTrue($resultado['usa_itemizada']);
        $this->assertSame(20000.0, $resultado['deduccion_aplicable']);
    }

    public function test_montos_2025_por_filing_status(): void
    {
        // Cifras post-OBBBA (One Big Beautiful Bill Act, jul-2025) — NO las
        // del ajuste por inflación original de Rev. Proc. 2024-40.
        $calculadora = new StandardDeductionCalculator;

        $this->assertSame(15750.0, $calculadora->calcular(2025, FilingStatus::Single, 0.0)['deduccion_estandar']);
        $this->assertSame(31500.0, $calculadora->calcular(2025, FilingStatus::MarriedFilingJointly, 0.0)['deduccion_estandar']);
        $this->assertSame(23625.0, $calculadora->calcular(2025, FilingStatus::HeadOfHousehold, 0.0)['deduccion_estandar']);
    }

    public function test_qss_usa_la_misma_tabla_que_mfj(): void
    {
        $resultado = (new StandardDeductionCalculator)->calcular(2025, FilingStatus::QualifyingSurvivingSpouse, 0.0);

        $this->assertSame(31500.0, $resultado['deduccion_estandar']);
    }
}
