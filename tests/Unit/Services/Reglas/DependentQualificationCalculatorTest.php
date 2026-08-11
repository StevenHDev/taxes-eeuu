<?php

namespace Tests\Unit\Services\Reglas;

use App\Services\Reglas\DependentQualificationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DependentQualificationCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function dependiente(array $overrides = []): array
    {
        return array_merge([
            'nombre_completo' => 'Dependiente de prueba',
            'fecha_nacimiento' => '2010-06-15',
            'relacion' => 'hijo',
            'meses_en_hogar' => 12,
            'estudiante_tiempo_completo' => false,
            'discapacitado' => false,
            'provee_mas_50_soporte_propio' => false,
            'ingreso_bruto_anual' => 0,
            'custodia_compartida_sin_conflicto' => false,
        ], $overrides);
    }

    public function test_qualifying_child_de_16_anos_es_elegible_ctc(): void
    {
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente(['fecha_nacimiento' => '2009-01-01']), // 16 años al 31/dic/2025
        ]);

        $this->assertSame('qualifying_child', $resultado['dependientes'][0]['calificacion']);
        $this->assertTrue($resultado['dependientes'][0]['elegible_ctc']);
        $this->assertFalse($resultado['dependientes'][0]['elegible_odc']);
        $this->assertSame(1, $resultado['conteo_ctc']);
    }

    public function test_qualifying_child_de_17_anos_es_odc_no_ctc(): void
    {
        // CTC exige menor de 17 al cierre del año — un QC de 17-18 años solo
        // habilita ODC.
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente(['fecha_nacimiento' => '2008-01-01']), // 17 años al 31/dic/2025
        ]);

        $this->assertSame('qualifying_child', $resultado['dependientes'][0]['calificacion']);
        $this->assertFalse($resultado['dependientes'][0]['elegible_ctc']);
        $this->assertTrue($resultado['dependientes'][0]['elegible_odc']);
    }

    public function test_frontera_exacta_19_anos_al_31_de_diciembre_no_es_qualifying_child(): void
    {
        // Nacido el 31/dic/2006: cumple exactamente 19 el 31/dic/2025 — ya no
        // es "menor de 19", y sin ser estudiante ni discapacitado, falla el
        // test de qualifying child (ingreso alto para que tampoco pase como
        // qualifying relative, y así aislar específicamente la frontera de edad).
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente(['fecha_nacimiento' => '2006-12-31', 'ingreso_bruto_anual' => 50000]),
        ]);

        $this->assertSame('ninguna', $resultado['dependientes'][0]['calificacion']);
    }

    public function test_un_dia_antes_de_cumplir_19_todavia_es_qualifying_child(): void
    {
        // Nacido el 1/ene/2007: al 31/dic/2025 todavía tiene 18 años.
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente(['fecha_nacimiento' => '2007-01-01']),
        ]);

        $this->assertSame('qualifying_child', $resultado['dependientes'][0]['calificacion']);
    }

    public function test_estudiante_tiempo_completo_de_22_anos_califica(): void
    {
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente(['fecha_nacimiento' => '2003-01-01', 'estudiante_tiempo_completo' => true]),
        ]);

        $this->assertSame('qualifying_child', $resultado['dependientes'][0]['calificacion']);
    }

    public function test_discapacitado_de_30_anos_califica_sin_limite_de_edad(): void
    {
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente(['fecha_nacimiento' => '1995-01-01', 'discapacitado' => true]),
        ]);

        $this->assertSame('qualifying_child', $resultado['dependientes'][0]['calificacion']);
    }

    public function test_qualifying_relative_con_ingreso_exactamente_en_el_limite_pasa(): void
    {
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente([
                'fecha_nacimiento' => '1980-01-01', // adulto, no puede ser QC
                'relacion' => 'padre',
                'ingreso_bruto_anual' => 5200,
            ]),
        ]);

        $this->assertSame('qualifying_relative', $resultado['dependientes'][0]['calificacion']);
    }

    public function test_qualifying_relative_con_ingreso_apenas_sobre_el_limite_falla(): void
    {
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente([
                'fecha_nacimiento' => '1980-01-01',
                'relacion' => 'padre',
                'ingreso_bruto_anual' => 5201,
            ]),
        ]);

        $this->assertSame('ninguna', $resultado['dependientes'][0]['calificacion']);
    }

    public function test_menos_de_6_meses_en_el_hogar_falla_qualifying_child(): void
    {
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente(['meses_en_hogar' => 3, 'ingreso_bruto_anual' => 10000]),
        ]);

        $this->assertSame('ninguna', $resultado['dependientes'][0]['calificacion']);
    }

    public function test_sin_relacion_valida_e_ingreso_alto_no_califica(): void
    {
        $resultado = (new DependentQualificationCalculator)->calcular(2025, [
            $this->dependiente(['relacion' => 'amigo', 'ingreso_bruto_anual' => 50000]),
        ]);

        $this->assertSame('ninguna', $resultado['dependientes'][0]['calificacion']);
        $this->assertSame(0, $resultado['conteo_ctc'] + $resultado['conteo_odc']);
    }
}
