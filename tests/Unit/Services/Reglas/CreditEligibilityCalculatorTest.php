<?php

namespace Tests\Unit\Services\Reglas;

use App\Enums\FilingStatus;
use App\Services\Reglas\CreditEligibilityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditEligibilityCalculatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function dependientesResultado(int $conteoCtc, int $conteoOdc, int $conteoCuidado = 0): array
    {
        return [
            'disponible' => true,
            'conteo_ctc' => $conteoCtc,
            'conteo_odc' => $conteoOdc,
            'conteo_cuidado' => $conteoCuidado,
        ];
    }

    public function test_phase_out_combinado_de_ctc_y_odc_no_los_reduce_por_separado(): void
    {
        // Finding A: CTC tentativo $2,200 + ODC tentativo $500 = $2,700. Un
        // AGI $210,000 (soltero, umbral $200,000) da un exceso de $10,000 →
        // reducción $500. El total combinado correcto es 2700-500=2200, NO
        // max(0, 2200-500) + max(0, 500-500) = 1700+0 = 1700 (versión incorrecta
        // que reduce cada crédito por separado).
        $resultado = (new CreditEligibilityCalculator())->calcular(
            2025,
            FilingStatus::Single,
            210000.0,
            $this->dependientesResultado(conteoCtc: 1, conteoOdc: 1),
            null,
        );

        $this->assertSame(2200.0, $resultado['total'] - $resultado['cuidado_dependientes']);
        $this->assertSame(500.0, $resultado['reduccion_por_agi']);
    }

    public function test_un_dolar_sobre_el_umbral_ya_cuesta_la_reduccion_completa(): void
    {
        // $200,001 de AGI (soltero) — $1 de exceso, pero ceil(1/1000)=1, así
        // que la reducción completa de $50 aplica igual (no floor, que daría 0).
        $resultado = (new CreditEligibilityCalculator())->calcular(
            2025,
            FilingStatus::Single,
            200001.0,
            $this->dependientesResultado(conteoCtc: 1, conteoOdc: 0),
            null,
        );

        $this->assertSame(50.0, $resultado['reduccion_por_agi']);
        $this->assertSame(2150.0, $resultado['ctc']);
    }

    public function test_agi_exactamente_en_el_umbral_no_tiene_reduccion(): void
    {
        $resultado = (new CreditEligibilityCalculator())->calcular(
            2025,
            FilingStatus::Single,
            200000.0,
            $this->dependientesResultado(conteoCtc: 1, conteoOdc: 0),
            null,
        );

        $this->assertSame(0.0, $resultado['reduccion_por_agi']);
        $this->assertSame(2200.0, $resultado['ctc']);
    }

    public function test_el_total_nunca_es_negativo(): void
    {
        // AGI muy por encima del umbral: la reducción tentativa supera el
        // crédito tentativo — el total debe quedar en 0, no negativo.
        $resultado = (new CreditEligibilityCalculator())->calcular(
            2025,
            FilingStatus::Single,
            500000.0,
            $this->dependientesResultado(conteoCtc: 1, conteoOdc: 0),
            null,
        );

        $this->assertSame(0.0, $resultado['ctc']);
        $this->assertGreaterThanOrEqual(0.0, $resultado['total']);
    }

    public function test_sin_gastos_de_cuidado_reportados_el_credito_es_cero(): void
    {
        $resultado = (new CreditEligibilityCalculator())->calcular(
            2025,
            FilingStatus::Single,
            50000.0,
            $this->dependientesResultado(conteoCtc: 1, conteoOdc: 0, conteoCuidado: 1),
            null,
        );

        $this->assertSame(0.0, $resultado['cuidado_dependientes']);
    }

    public function test_el_tope_de_gastos_cambia_segun_1_o_2_mas_dependientes(): void
    {
        $unDependiente = (new CreditEligibilityCalculator())->calcular(
            2025,
            FilingStatus::Single,
            10000.0, // AGI bajo → porcentaje máximo (35%)
            $this->dependientesResultado(conteoCtc: 0, conteoOdc: 0, conteoCuidado: 1),
            ['monto_anual' => 10000],
        );

        // Tope de 1 persona: $3,000 × 35% = $1,050.
        $this->assertSame(1050.0, $unDependiente['cuidado_dependientes']);

        $dosDependientes = (new CreditEligibilityCalculator())->calcular(
            2025,
            FilingStatus::Single,
            10000.0,
            $this->dependientesResultado(conteoCtc: 0, conteoOdc: 0, conteoCuidado: 2),
            ['monto_anual' => 10000],
        );

        // Tope de 2+ personas: $6,000 × 35% = $2,100.
        $this->assertSame(2100.0, $dosDependientes['cuidado_dependientes']);
    }

    public function test_porcentaje_de_cuidado_interpola_en_los_extremos_y_a_la_mitad(): void
    {
        $calculadora = new CreditEligibilityCalculator();

        $bajo = $calculadora->calcular(2025, FilingStatus::Single, 15000.0, $this->dependientesResultado(0, 0, 1), ['monto_anual' => 3000]);
        $this->assertSame(1050.0, $bajo['cuidado_dependientes']); // 35% de 3000

        $alto = $calculadora->calcular(2025, FilingStatus::Single, 43000.0, $this->dependientesResultado(0, 0, 1), ['monto_anual' => 3000]);
        $this->assertSame(600.0, $alto['cuidado_dependientes']); // 20% de 3000

        $medio = $calculadora->calcular(2025, FilingStatus::Single, 29000.0, $this->dependientesResultado(0, 0, 1), ['monto_anual' => 3000]);
        $this->assertEqualsWithDelta(825.0, $medio['cuidado_dependientes'], 0.0001); // 27.5% de 3000 (punto medio exacto)
    }
}
