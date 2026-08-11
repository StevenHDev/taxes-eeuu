<?php

namespace Tests\Unit\Services\Reglas;

use App\Enums\FilingStatus;
use App\Services\Reglas\AdditionalMedicareTaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdditionalMedicareTaxCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_bajo_el_umbral_no_hay_impuesto(): void
    {
        $resultado = (new AdditionalMedicareTaxCalculator)->calcular(2025, FilingStatus::Single, 100000.0, 0.0);

        $this->assertSame(0.0, $resultado['impuesto']);
    }

    public function test_por_encima_del_umbral_soltero(): void
    {
        // Umbral soltero $200,000. Exceso de $50,000 × 0.9% = $450.
        $resultado = (new AdditionalMedicareTaxCalculator)->calcular(2025, FilingStatus::Single, 250000.0, 0.0);

        $this->assertEqualsWithDelta(450.0, $resultado['impuesto'], 0.01);
    }

    public function test_mfj_usa_un_umbral_distinto(): void
    {
        // Umbral MFJ $250,000. Exceso de $10,000 × 0.9% = $90.
        $resultado = (new AdditionalMedicareTaxCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, 260000.0, 0.0);

        $this->assertSame(90.0, $resultado['impuesto']);
    }

    public function test_combina_salarios_medicare_con_base_gravable_de_se(): void
    {
        // 150,000 salarios + 100,000 base SE = 250,000 combinado. Exceso de
        // $50,000 sobre el umbral soltero × 0.9% = $450.
        $resultado = (new AdditionalMedicareTaxCalculator)->calcular(2025, FilingStatus::Single, 150000.0, 100000.0);

        $this->assertEqualsWithDelta(450.0, $resultado['impuesto'], 0.01);
    }
}
