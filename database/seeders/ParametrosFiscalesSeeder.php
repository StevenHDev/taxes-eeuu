<?php

namespace Database\Seeders;

use App\Models\ParametroFiscal;
use App\Support\ParametrosFiscales;
use Illuminate\Database\Seeder;

/**
 * Montos y umbrales del IRS para tax_year 2025 — confirmados por búsqueda web
 * (no inventados), ver docs/plan-desarrollo-fases.md sección 2 para las
 * fuentes. Un CPA debe validar estas cifras antes de usarlas para preparar
 * declaraciones reales; son las cifras públicas vigentes al momento de
 * construir esta fase, pero esta plataforma no reemplaza el criterio
 * profesional de un preparador.
 */
class ParametrosFiscalesSeeder extends Seeder
{
    private const TAX_YEAR = 2025;

    public function run(): void
    {
        foreach ($this->valores() as [$categoria, $clave, $valor]) {
            ParametroFiscal::query()->firstOrCreate(
                ['tax_year' => self::TAX_YEAR, 'categoria' => $categoria, 'clave' => $clave],
                ['valor' => $valor],
            );
        }

        ParametrosFiscales::invalidate();
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function valores(): array
    {
        return [
            // Child Tax Credit: $2,200/dependiente calificado, phase-out desde
            // $200k (soltero/HOH) o $400k (MFJ), $50 menos por cada $1,000 de
            // exceso (redondeado hacia arriba — ver CreditEligibilityCalculator).
            ['credito_ctc', 'monto_por_dependiente', 2200],
            ['credito_ctc', 'phase_out_umbral_soltero', 200000],
            ['credito_ctc', 'phase_out_umbral_mfj', 400000],
            ['credito_ctc', 'reduccion_por_1000_exceso', 50],

            // Credit for Other Dependents: $500/dependiente, mismo phase-out
            // que el CTC (se combinan en un solo cálculo, no por separado).
            ['credito_odc', 'monto_por_dependiente', 500],
            ['credito_odc', 'phase_out_umbral_soltero', 200000],
            ['credito_odc', 'phase_out_umbral_mfj', 400000],
            ['credito_odc', 'reduccion_por_1000_exceso', 50],

            // Prueba de ingreso bruto para "qualifying relative".
            ['dependiente_calificado', 'limite_ingreso_bruto_pariente_calificado', 5200],

            // Deducción estándar — sembrada para uso futuro, ninguna
            // calculadora la usa todavía (no es código muerto: es forward-looking).
            ['deduccion_estandar', 'monto_soltero', 15000],
            ['deduccion_estandar', 'monto_mfj', 30000],
            ['deduccion_estandar', 'monto_hoh', 22500],

            // Child and Dependent Care Credit (Form 2441). El porcentaje real
            // del IRS es una tabla escalonada; acá se interpola linealmente
            // entre estos dos extremos como simplificación documentada — ver
            // CreditEligibilityCalculator.
            ['credito_cuidado_dependientes', 'tope_gastos_1_persona', 3000],
            ['credito_cuidado_dependientes', 'tope_gastos_2_mas_personas', 6000],
            ['credito_cuidado_dependientes', 'porcentaje_maximo', 0.35],
            ['credito_cuidado_dependientes', 'porcentaje_minimo', 0.20],
            ['credito_cuidado_dependientes', 'agi_umbral_porcentaje_maximo', 15000],
            ['credito_cuidado_dependientes', 'agi_umbral_porcentaje_minimo', 43000],
            ['credito_cuidado_dependientes', 'edad_limite_dependiente', 13],
        ];
    }
}
