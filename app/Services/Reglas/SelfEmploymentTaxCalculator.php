<?php

namespace App\Services\Reglas;

use App\Support\ParametrosFiscales;

/**
 * Schedule SE — impuesto de autoempleo sobre el ingreso neto de negocio
 * propio (schedule_c) y/o granja (schedule_f). Fórmula fija por ley (IRC
 * §1401): 92.35% del ingreso neto es la base gravable; 12.4% de Social
 * Security hasta el tope anual de salario, más 2.9% de Medicare sin tope.
 *
 * Limitación documentada: no coordina el tope de Social Security con
 * salarios W-2 ya sujetos a esa retención (si el cliente además es empleado,
 * el tope real disponible para SE es menor) — asume que todo el tope
 * corresponde al ingreso de autoempleo. Tampoco reparte el resultado por
 * cónyuge cuando hay más de un negocio en un matrimonio con MFJ; el ingreso
 * neto que recibe ya debe venir sumado/separado por quien llama.
 */
class SelfEmploymentTaxCalculator
{
    private const TASA_SOCIAL_SECURITY = 0.124;

    private const TASA_MEDICARE = 0.029;

    private const BASE_GRAVABLE = 0.9235;

    /**
     * @return array{disponible: bool, motivo_no_disponible: ?string, ingreso_neto_se?: float, base_gravable?: float, impuesto_se?: float, mitad_deducible?: float}
     */
    public function calcular(int $taxYear, float $ingresoNetoAutoempleo): array
    {
        // Por debajo de $400 no hay obligación de declarar SE tax (umbral
        // fijo por ley, no ajustado por inflación).
        if ($ingresoNetoAutoempleo < 400) {
            return [
                'disponible' => true,
                'motivo_no_disponible' => null,
                'ingreso_neto_se' => $ingresoNetoAutoempleo,
                'base_gravable' => 0.0,
                'impuesto_se' => 0.0,
                'mitad_deducible' => 0.0,
            ];
        }

        $topeSalarioSs = (float) ParametrosFiscales::valorRequerido($taxYear, 'self_employment_tax', 'tope_salario_social_security');

        $baseGravable = $ingresoNetoAutoempleo * self::BASE_GRAVABLE;

        $porcionSocialSecurity = min($baseGravable, $topeSalarioSs) * self::TASA_SOCIAL_SECURITY;
        $porcionMedicare = $baseGravable * self::TASA_MEDICARE;

        $impuestoSe = $porcionSocialSecurity + $porcionMedicare;

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'ingreso_neto_se' => $ingresoNetoAutoempleo,
            'base_gravable' => $baseGravable,
            'impuesto_se' => $impuestoSe,
            // Mitad del SE tax es deducible como ajuste al ingreso (Schedule 1,
            // línea 15) — ver AgiCalculator, que la suma a `ajustes_ingreso`.
            'mitad_deducible' => $impuestoSe / 2,
        ];
    }
}
