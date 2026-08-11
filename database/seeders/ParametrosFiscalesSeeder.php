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

            // Deducción estándar 2025 — corregida por la "One Big Beautiful
            // Bill Act" (OBBBA, firmada 4-jul-2025), que subió estos montos
            // POR ENCIMA del ajuste por inflación original de Rev. Proc.
            // 2024-40 ($15,000/$30,000/$22,500) — esa cifra quedó obsoleta a
            // mitad de año. Confirmado con las instrucciones oficiales del
            // Form 1040 (irs.gov/instructions/i1040gi) y múltiples fuentes
            // independientes (Tax Foundation, H&R Block, IRS Newsroom del
            // 2025 tax year 2026 release). Usada por StandardDeductionCalculator.
            ['deduccion_estandar', 'monto_soltero', 15750],
            ['deduccion_estandar', 'monto_mfj', 31500],
            ['deduccion_estandar', 'monto_hoh', 23625],

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

            // Tax Rate Tables 2025 (IRC §1(j), Rev. Proc. 2024-40 tablas 1-3 —
            // leídas directo del PDF oficial, no de un resumen). OBBBA no
            // modificó estos tramos ni sus montos (solo hizo permanentes las
            // tasas de la TCJA que iban a expirar) — a diferencia de la
            // deducción estándar y el rango de QBI, estos números NO
            // necesitaron corrección. QSS usa la misma tabla que MFJ (regla
            // del IRC, ver TaxableIncomeAndTaxCalculator), así que no tiene
            // clave propia. Cada tramo: {desde, tasa} ordenado ascendente.
            ['tax_brackets', 'single', [
                ['desde' => 0, 'tasa' => 0.10],
                ['desde' => 11925, 'tasa' => 0.12],
                ['desde' => 48475, 'tasa' => 0.22],
                ['desde' => 103350, 'tasa' => 0.24],
                ['desde' => 197300, 'tasa' => 0.32],
                ['desde' => 250525, 'tasa' => 0.35],
                ['desde' => 626350, 'tasa' => 0.37],
            ]],
            ['tax_brackets', 'mfj', [
                ['desde' => 0, 'tasa' => 0.10],
                ['desde' => 23850, 'tasa' => 0.12],
                ['desde' => 96950, 'tasa' => 0.22],
                ['desde' => 206700, 'tasa' => 0.24],
                ['desde' => 394600, 'tasa' => 0.32],
                ['desde' => 501050, 'tasa' => 0.35],
                ['desde' => 751600, 'tasa' => 0.37],
            ]],
            ['tax_brackets', 'hoh', [
                ['desde' => 0, 'tasa' => 0.10],
                ['desde' => 17000, 'tasa' => 0.12],
                ['desde' => 64850, 'tasa' => 0.22],
                ['desde' => 103350, 'tasa' => 0.24],
                ['desde' => 197300, 'tasa' => 0.32],
                ['desde' => 250500, 'tasa' => 0.35],
                ['desde' => 626350, 'tasa' => 0.37],
            ]],

            // Self-employment tax (Schedule SE) — tope de salario de Social
            // Security 2025 anunciado por la SSA en oct-2024 (no lo fija el
            // IRS ni Rev. Proc. 2024-40, es un anuncio separado). Usado por
            // SelfEmploymentTaxCalculator.
            ['self_employment_tax', 'tope_salario_social_security', 176100],

            // Additional Medicare Tax (Form 8959) — umbrales fijos por ley
            // (IRC §3101(b)(2)) desde 2013, NO ajustados por inflación ni
            // tocados por OBBBA. Usado por AdditionalMedicareTaxCalculator.
            ['additional_medicare_tax', 'umbral_soltero', 200000],
            ['additional_medicare_tax', 'umbral_mfj', 250000],

            // Net Investment Income Tax (Form 8960) — mismos umbrales fijos
            // desde 2013 (IRC §1411), sin ajuste por inflación ni cambio de
            // OBBBA. Usado por NiitCalculator.
            ['niit', 'umbral_soltero', 200000],
            ['niit', 'umbral_mfj', 250000],

            // Qualified Business Income (Form 8995, §199A) — umbral 2025
            // donde empieza cualquier limitación (Rev. Proc. 2024-40 .27).
            // OBBBA NO cambió este umbral — solo amplió el RANGO de phase-out
            // (que esta plataforma no modela, ver QbiCalculator, limitación
            // documentada). Usado por QbiCalculator.
            ['qbi', 'umbral_soltero', 197300],
            ['qbi', 'umbral_mfj', 394600],
        ];
    }
}
