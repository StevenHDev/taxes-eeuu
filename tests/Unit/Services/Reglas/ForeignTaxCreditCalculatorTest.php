<?php

namespace Tests\Unit\Services\Reglas;

use App\Enums\FilingStatus;
use App\Services\Reglas\ForeignTaxCreditCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4 del plan de cierre de brecha GTS — Foreign Tax Credit simplificado
 * (solo la elección de minimis sin Form 1116, IRC §904(j)).
 */
class ForeignTaxCreditCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_impuesto_extranjero_pagado_no_hay_credito(): void
    {
        $resultado = (new ForeignTaxCreditCalculator)->calcular(2025, FilingStatus::Single, 0.0);

        $this->assertSame(0.0, $resultado['credito']);
        $this->assertFalse($resultado['requiere_form_1116']);
    }

    public function test_bajo_el_umbral_de_soltero_el_credito_es_el_monto_pagado(): void
    {
        $resultado = (new ForeignTaxCreditCalculator)->calcular(2025, FilingStatus::Single, 250.0);

        $this->assertSame(250.0, $resultado['credito']);
        $this->assertFalse($resultado['requiere_form_1116']);
    }

    public function test_justo_en_el_umbral_de_soltero_todavia_no_requiere_form_1116(): void
    {
        $resultado = (new ForeignTaxCreditCalculator)->calcular(2025, FilingStatus::Single, 300.0);

        $this->assertSame(300.0, $resultado['credito']);
        $this->assertFalse($resultado['requiere_form_1116']);
    }

    public function test_por_encima_del_umbral_de_soltero_el_credito_queda_en_cero(): void
    {
        $resultado = (new ForeignTaxCreditCalculator)->calcular(2025, FilingStatus::Single, 301.0);

        $this->assertSame(0.0, $resultado['credito']);
        $this->assertTrue($resultado['requiere_form_1116']);
    }

    public function test_casados_presentando_juntos_usan_el_umbral_de_600(): void
    {
        $bajoElUmbral = (new ForeignTaxCreditCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, 600.0);
        $porEncima = (new ForeignTaxCreditCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, 601.0);

        $this->assertSame(600.0, $bajoElUmbral['credito']);
        $this->assertFalse($bajoElUmbral['requiere_form_1116']);
        $this->assertSame(0.0, $porEncima['credito']);
        $this->assertTrue($porEncima['requiere_form_1116']);
    }

    /**
     * Mismo agrupamiento que StandardDeductionCalculator: QSS usa la misma
     * tabla que MFJ.
     */
    public function test_qss_usa_el_mismo_umbral_que_mfj(): void
    {
        $resultado = (new ForeignTaxCreditCalculator)->calcular(2025, FilingStatus::QualifyingSurvivingSpouse, 600.0);

        $this->assertSame(600.0, $resultado['credito']);
        $this->assertFalse($resultado['requiere_form_1116']);
    }

    public function test_head_of_household_usa_el_umbral_de_300_no_el_de_600(): void
    {
        $resultado = (new ForeignTaxCreditCalculator)->calcular(2025, FilingStatus::HeadOfHousehold, 450.0);

        $this->assertSame(0.0, $resultado['credito']);
        $this->assertTrue($resultado['requiere_form_1116']);
    }
}
