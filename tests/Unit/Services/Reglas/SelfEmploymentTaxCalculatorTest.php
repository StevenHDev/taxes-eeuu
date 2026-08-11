<?php

namespace Tests\Unit\Services\Reglas;

use App\Services\Reglas\SelfEmploymentTaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfEmploymentTaxCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_bajo_400_no_genera_impuesto(): void
    {
        $resultado = (new SelfEmploymentTaxCalculator)->calcular(2025, 300.0);

        $this->assertSame(0.0, $resultado['impuesto_se']);
        $this->assertSame(0.0, $resultado['mitad_deducible']);
    }

    public function test_perdida_neta_no_genera_impuesto(): void
    {
        $resultado = (new SelfEmploymentTaxCalculator)->calcular(2025, -5000.0);

        $this->assertSame(0.0, $resultado['impuesto_se']);
    }

    public function test_calculo_estandar_bajo_el_tope_de_social_security(): void
    {
        // Base gravable: 50,000 × 92.35% = 46,175. SS: 46,175 × 12.4% =
        // 5,725.70. Medicare: 46,175 × 2.9% = 1,339.075. Total = 7,064.775.
        $resultado = (new SelfEmploymentTaxCalculator)->calcular(2025, 50000.0);

        $this->assertEqualsWithDelta(46175.0, $resultado['base_gravable'], 0.01);
        $this->assertEqualsWithDelta(7064.775, $resultado['impuesto_se'], 0.01);
        $this->assertEqualsWithDelta(3532.3875, $resultado['mitad_deducible'], 0.01);
    }

    public function test_por_encima_del_tope_limita_solo_la_porcion_de_social_security(): void
    {
        // Base gravable: 250,000 × 92.35% = 230,875 — por encima del tope
        // 2025 ($176,100). SS: 176,100 × 12.4% = 21,836.40 (topado).
        // Medicare: 230,875 × 2.9% = 6,695.375 (sin tope). Total = 28,531.775.
        $resultado = (new SelfEmploymentTaxCalculator)->calcular(2025, 250000.0);

        $this->assertEqualsWithDelta(28531.775, $resultado['impuesto_se'], 0.01);
    }
}
