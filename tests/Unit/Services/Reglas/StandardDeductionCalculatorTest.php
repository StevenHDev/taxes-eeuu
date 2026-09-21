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
        $resultado = (new StandardDeductionCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, ['intereses_hipotecarios' => 20000.0]);

        $this->assertFalse($resultado['usa_itemizada']);
        $this->assertSame(31500.0, $resultado['deduccion_aplicable']);
    }

    public function test_usa_la_itemizada_cuando_es_mayor(): void
    {
        $resultado = (new StandardDeductionCalculator)->calcular(2025, FilingStatus::Single, ['intereses_hipotecarios' => 20000.0]);

        $this->assertTrue($resultado['usa_itemizada']);
        $this->assertSame(20000.0, $resultado['deduccion_aplicable']);
    }

    public function test_montos_2025_por_filing_status(): void
    {
        // Cifras post-OBBBA (One Big Beautiful Bill Act, jul-2025) — NO las
        // del ajuste por inflación original de Rev. Proc. 2024-40.
        $calculadora = new StandardDeductionCalculator;

        $this->assertSame(15750.0, $calculadora->calcular(2025, FilingStatus::Single, null)['deduccion_estandar']);
        $this->assertSame(31500.0, $calculadora->calcular(2025, FilingStatus::MarriedFilingJointly, null)['deduccion_estandar']);
        $this->assertSame(23625.0, $calculadora->calcular(2025, FilingStatus::HeadOfHousehold, null)['deduccion_estandar']);
    }

    public function test_qss_usa_la_misma_tabla_que_mfj(): void
    {
        $resultado = (new StandardDeductionCalculator)->calcular(2025, FilingStatus::QualifyingSurvivingSpouse, null);

        $this->assertSame(31500.0, $resultado['deduccion_estandar']);
    }

    /**
     * Fase 4 del plan de cierre de brecha GTS: deducciones pasó de un único
     * Number suelto a un objeto con subcampos por categoría — el calculador
     * es quien suma, no se le pasa ya sumado.
     */
    public function test_suma_todos_los_subcampos_para_la_deduccion_itemizada(): void
    {
        $resultado = (new StandardDeductionCalculator)->calcular(2025, FilingStatus::Single, [
            'intereses_hipotecarios' => 8000.0,
            'impuestos_propiedad' => 4000.0,
            'donaciones_efectivo' => 2000.0,
            'donaciones_bienes' => 500.0,
            'gastos_medicos' => 1000.0,
            'intereses_inversion' => 300.0,
            'perdidas_desastre' => 0.0,
        ]);

        $this->assertSame(15800.0, $resultado['deduccion_itemizada']);
    }

    public function test_null_se_trata_como_cero_nunca_truena(): void
    {
        $resultado = (new StandardDeductionCalculator)->calcular(2025, FilingStatus::Single, null);

        $this->assertSame(0.0, $resultado['deduccion_itemizada']);
        $this->assertFalse($resultado['usa_itemizada']);
    }
}
