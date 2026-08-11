<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;
use App\Support\ParametrosFiscales;

/**
 * Net Investment Income Tax (Form 8960) — 3.8% sobre el MENOR entre el
 * ingreso neto de inversión y el exceso de MAGI sobre el umbral por filing
 * status. Tasa y umbrales fijos por ley (IRC §1411) desde 2013, no ajustados
 * por inflación.
 *
 * QSS usa el mismo umbral que Single/HOH — regla del IRC, no una cifra que
 * cambie por año.
 *
 * Limitación documentada: "ingreso neto de inversión" acá es intereses +
 * dividendos + ganancias de capital + renta de alquiler (`ingresos_renta` de
 * cada schedule_e declarada) — no distingue alquiler activo/pasivo material
 * participation, ni resta gastos de inversión deducibles; MAGI se aproxima
 * con AGI (la diferencia real solo aplica a exclusión de ingreso extranjero,
 * caso raro fuera del alcance actual).
 */
class NiitCalculator
{
    private const TASA = 0.038;

    /**
     * @return array{disponible: bool, motivo_no_disponible: ?string, ingreso_neto_inversion?: float, exceso_magi?: float, impuesto?: float}
     */
    public function calcular(int $taxYear, FilingStatus $filingStatus, float $magi, float $ingresoNetoInversion): array
    {
        $clave = $filingStatus === FilingStatus::MarriedFilingJointly ? 'umbral_mfj' : 'umbral_soltero';
        $umbral = (float) ParametrosFiscales::valorRequerido($taxYear, 'niit', $clave);

        $excesoMagi = max(0.0, $magi - $umbral);
        $base = max(0.0, min($ingresoNetoInversion, $excesoMagi));

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'ingreso_neto_inversion' => $ingresoNetoInversion,
            'exceso_magi' => $excesoMagi,
            'impuesto' => $base * self::TASA,
        ];
    }
}
