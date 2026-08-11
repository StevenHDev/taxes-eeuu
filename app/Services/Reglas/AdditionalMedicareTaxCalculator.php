<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;
use App\Support\ParametrosFiscales;

/**
 * Form 8959 — 0.9% adicional sobre salarios Medicare + ingreso neto de
 * autoempleo (ya ajustado al 92.35%) que exceda el umbral por filing status.
 * Tasa y umbrales fijos por ley (IRC §3101(b)(2)) desde 2013, no ajustados
 * por inflación — a diferencia de casi todos los demás parámetros del motor.
 *
 * QSS usa el mismo umbral que Single/HOH (todo lo que no sea MFJ) — regla
 * del IRC, no una cifra que cambie por año.
 *
 * Limitación documentada: no descuenta la retención adicional que un
 * empleador ya pudo haber aplicado (W-2 no tiene un campo separado para
 * eso hoy) — calcula la obligación total, no el saldo pendiente de esa
 * porción específica.
 */
class AdditionalMedicareTaxCalculator
{
    private const TASA = 0.009;

    /**
     * @return array{disponible: bool, motivo_no_disponible: ?string, base_combinada?: float, umbral?: float, impuesto?: float}
     */
    public function calcular(int $taxYear, FilingStatus $filingStatus, float $salariosMedicare, float $baseGravableSe): array
    {
        $clave = $filingStatus === FilingStatus::MarriedFilingJointly ? 'umbral_mfj' : 'umbral_soltero';
        $umbral = (float) ParametrosFiscales::valorRequerido($taxYear, 'additional_medicare_tax', $clave);

        $baseCombinada = $salariosMedicare + $baseGravableSe;
        $exceso = max(0.0, $baseCombinada - $umbral);

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'base_combinada' => $baseCombinada,
            'umbral' => $umbral,
            'impuesto' => $exceso * self::TASA,
        ];
    }
}
