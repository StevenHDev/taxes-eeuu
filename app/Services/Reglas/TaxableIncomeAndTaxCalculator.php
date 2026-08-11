<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;
use App\Support\ParametrosFiscales;

/**
 * Ingreso gravable (Form 1040 línea 15) + impuesto antes de créditos (línea
 * 16), aplicando los tramos marginales federales (IRC §1) sobre el ingreso
 * gravable.
 *
 * Los tramos se guardan en `parametros_fiscales` como `categoria: 'tax_brackets'`,
 * `clave: <filing status>`, `valor` = lista de `{desde, tasa}` ordenada
 * ascendente por `desde` (ej. `[{"desde":0,"tasa":0.10}, {"desde":11925,"tasa":0.12}, ...]`)
 * — nunca hardcodeados acá, igual que el resto del motor. QSS usa la misma
 * tabla que MFJ (regla del IRC, no una cifra que cambie por año), así que
 * comparte la clave `mfj`.
 *
 * Limitación documentada: usa siempre la tabla de tramos ordinarios — no
 * aplica el Qualified Dividends and Capital Gain Tax Worksheet (tasa
 * preferencial 0%/15%/20% sobre dividendos calificados y ganancias de
 * capital de largo plazo). Sobrestima el impuesto de cualquier cliente con
 * ganancias de capital de largo plazo o dividendos calificados significativos.
 */
class TaxableIncomeAndTaxCalculator
{
    /**
     * @return array{disponible: bool, motivo_no_disponible: ?string, ingreso_gravable?: float, impuesto?: float}
     */
    public function calcular(
        int $taxYear,
        FilingStatus $filingStatus,
        float $agi,
        float $deduccionAplicable,
        float $qbiDeduccion,
    ): array {
        $ingresoGravable = max(0.0, $agi - $deduccionAplicable - $qbiDeduccion);

        $clave = $filingStatus === FilingStatus::QualifyingSurvivingSpouse ? 'mfj' : $filingStatus->value;
        $tramos = ParametrosFiscales::valorRequerido($taxYear, 'tax_brackets', $clave);

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'ingreso_gravable' => $ingresoGravable,
            'impuesto' => $this->aplicarTramos($ingresoGravable, $tramos),
        ];
    }

    /**
     * @param  array<int, array{desde: float|int, tasa: float}>  $tramos  ordenados ascendente por 'desde'
     */
    private function aplicarTramos(float $ingresoGravable, array $tramos): float
    {
        $impuesto = 0.0;

        foreach ($tramos as $i => $tramo) {
            $desde = (float) $tramo['desde'];

            if ($ingresoGravable <= $desde) {
                break;
            }

            $siguienteDesde = isset($tramos[$i + 1]) ? (float) $tramos[$i + 1]['desde'] : null;
            $techoTramo = $siguienteDesde !== null ? min($ingresoGravable, $siguienteDesde) : $ingresoGravable;

            $impuesto += ($techoTramo - $desde) * (float) $tramo['tasa'];
        }

        return $impuesto;
    }
}
