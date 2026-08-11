<?php

namespace Tests\Unit\Services\Reglas;

use App\Enums\FilingStatus;
use App\Services\Reglas\QbiCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QbiCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_qbi_cero_no_genera_deduccion(): void
    {
        $resultado = (new QbiCalculator)->calcular(2025, FilingStatus::Single, 0.0, 80000.0, 0.0);

        $this->assertSame(0.0, $resultado['deduccion']);
        $this->assertFalse($resultado['requiere_revision_manual']);
    }

    public function test_deduccion_simple_20_porciento_bajo_el_limite_de_taxable_income(): void
    {
        // QBI 50,000 × 20% = 10,000. Límite: 80,000 × 20% = 16,000 — no ata.
        $resultado = (new QbiCalculator)->calcular(2025, FilingStatus::Single, 50000.0, 80000.0, 0.0);

        $this->assertSame(10000.0, $resultado['deduccion']);
    }

    public function test_el_limite_de_taxable_income_ata_cuando_es_menor(): void
    {
        // QBI 100,000 × 20% = 20,000 tentativo, pero el límite es
        // 30,000 × 20% = 6,000 — el límite gana.
        $resultado = (new QbiCalculator)->calcular(2025, FilingStatus::Single, 100000.0, 30000.0, 0.0);

        $this->assertSame(6000.0, $resultado['deduccion']);
    }

    public function test_la_ganancia_de_capital_reduce_el_limite(): void
    {
        // Límite: (80,000 - 50,000) × 20% = 6,000, menor que el tentativo
        // de QBI (50,000 × 20% = 10,000) — la ganancia de capital ata.
        $resultado = (new QbiCalculator)->calcular(2025, FilingStatus::Single, 50000.0, 80000.0, 50000.0);

        $this->assertSame(6000.0, $resultado['deduccion']);
    }

    public function test_marca_revision_manual_por_encima_del_umbral(): void
    {
        // Umbral soltero 2025: $197,300.
        $resultado = (new QbiCalculator)->calcular(2025, FilingStatus::Single, 50000.0, 250000.0, 0.0);

        $this->assertTrue($resultado['requiere_revision_manual']);
    }

    public function test_no_marca_revision_manual_bajo_el_umbral(): void
    {
        $resultado = (new QbiCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, 50000.0, 100000.0, 0.0);

        $this->assertFalse($resultado['requiere_revision_manual']);
    }
}
