<?php

namespace Tests\Unit\Services\Reglas;

use App\Enums\FilingStatus;
use App\Services\Reglas\NiitCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NiitCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_bajo_el_umbral_no_hay_impuesto(): void
    {
        $resultado = (new NiitCalculator)->calcular(2025, FilingStatus::Single, 150000.0, 50000.0);

        $this->assertSame(0.0, $resultado['impuesto']);
    }

    public function test_impuesto_es_el_menor_entre_inversion_y_exceso_de_magi(): void
    {
        // MAGI 250,000, umbral soltero 200,000 → exceso 50,000. Inversión
        // 80,000 > exceso → la base es el exceso (50,000) × 3.8% = 1,900.
        $resultado = (new NiitCalculator)->calcular(2025, FilingStatus::Single, 250000.0, 80000.0);

        $this->assertSame(50000.0, $resultado['exceso_magi']);
        $this->assertSame(1900.0, $resultado['impuesto']);
    }

    public function test_usa_la_inversion_cuando_es_menor_que_el_exceso(): void
    {
        // MAGI 300,000 → exceso 100,000. Inversión 20,000 < exceso → la base
        // es la inversión (20,000) × 3.8% = 760.
        $resultado = (new NiitCalculator)->calcular(2025, FilingStatus::Single, 300000.0, 20000.0);

        $this->assertSame(760.0, $resultado['impuesto']);
    }

    public function test_mfj_usa_un_umbral_distinto(): void
    {
        $resultado = (new NiitCalculator)->calcular(2025, FilingStatus::MarriedFilingJointly, 260000.0, 5000.0);

        // Umbral MFJ 250,000 → exceso 10,000. Inversión 5,000 < exceso.
        $this->assertSame(190.0, $resultado['impuesto']);
    }
}
