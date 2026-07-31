<?php

namespace Tests\Unit\Services\Reglas;

use App\Enums\FilingStatus;
use App\Services\Reglas\FilingStatusCalculator;
use PHPUnit\Framework\TestCase;

class FilingStatusCalculatorTest extends TestCase
{
    private function estadoCivil(array $overrides = []): array
    {
        return array_merge([
            'casado_al_31_dic' => false,
            'convivio_conyuge_ultimos_6_meses' => false,
            'costeo_mas_mitad_hogar' => false,
            'existe_persona_calificable' => false,
            'conyuge_fallecio_en_anio' => false,
            'anio_fallecimiento_conyuge' => null,
        ], $overrides);
    }

    public function test_casado_es_mfj(): void
    {
        $resultado = (new FilingStatusCalculator())->calcular(
            2025,
            $this->estadoCivil(['casado_al_31_dic' => true]),
            existeQualifyingChild: false,
            existeAlgunDependienteCalificado: false,
        );

        $this->assertSame(FilingStatus::MarriedFilingJointly->value, $resultado['estado']);
    }

    public function test_viudo_en_el_ano_del_fallecimiento_sigue_siendo_mfj(): void
    {
        // El año en que murió el cónyuge todavía se declara MFJ — QSS solo
        // aplica a los dos años SIGUIENTES al fallecimiento.
        $resultado = (new FilingStatusCalculator())->calcular(
            2025,
            $this->estadoCivil([
                'conyuge_fallecio_en_anio' => true,
                'anio_fallecimiento_conyuge' => 2025,
                'costeo_mas_mitad_hogar' => true,
            ]),
            existeQualifyingChild: true,
            existeAlgunDependienteCalificado: true,
        );

        $this->assertSame(FilingStatus::MarriedFilingJointly->value, $resultado['estado']);
    }

    public function test_viudo_dentro_de_los_dos_anos_con_qualifying_child_es_qss(): void
    {
        foreach ([1, 2] as $delta) {
            $resultado = (new FilingStatusCalculator())->calcular(
                2024 + $delta,
                $this->estadoCivil([
                    'conyuge_fallecio_en_anio' => true,
                    'anio_fallecimiento_conyuge' => 2024,
                    'costeo_mas_mitad_hogar' => true,
                ]),
                existeQualifyingChild: true,
                existeAlgunDependienteCalificado: true,
            );

            $this->assertSame(FilingStatus::QualifyingSurvivingSpouse->value, $resultado['estado'], "delta={$delta}");
        }
    }

    public function test_viudo_fuera_del_periodo_de_dos_anos_no_es_qss(): void
    {
        $resultado = (new FilingStatusCalculator())->calcular(
            2027, // delta = 3
            $this->estadoCivil([
                'conyuge_fallecio_en_anio' => true,
                'anio_fallecimiento_conyuge' => 2024,
                'costeo_mas_mitad_hogar' => true,
            ]),
            existeQualifyingChild: true,
            existeAlgunDependienteCalificado: true,
        );

        $this->assertSame(FilingStatus::HeadOfHousehold->value, $resultado['estado']);
    }

    public function test_soltero_con_dependiente_calificado_y_costeo_es_hoh(): void
    {
        $resultado = (new FilingStatusCalculator())->calcular(
            2025,
            $this->estadoCivil(['costeo_mas_mitad_hogar' => true]),
            existeQualifyingChild: false,
            existeAlgunDependienteCalificado: true,
        );

        $this->assertSame(FilingStatus::HeadOfHousehold->value, $resultado['estado']);
    }

    public function test_soltero_sin_dependientes_ni_costeo_es_single(): void
    {
        $resultado = (new FilingStatusCalculator())->calcular(
            2025,
            $this->estadoCivil(),
            existeQualifyingChild: false,
            existeAlgunDependienteCalificado: false,
        );

        $this->assertSame(FilingStatus::Single->value, $resultado['estado']);
    }
}
