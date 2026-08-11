<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;
use App\Support\ParametrosFiscales;

/**
 * Compara la deducción estándar (Form 1040 línea 12a-12c) contra la
 * deducción itemizada que el cliente ya reportó en `form_1040.deducciones`
 * (Schedule A, hoy un número plano sin desglose por categoría) y devuelve la
 * mayor — el contribuyente usa la que más le conviene, nunca las dos.
 *
 * QSS usa la misma tabla que MFJ (regla del IRC, no una cifra que cambie por
 * año) — por eso no tiene su propia clave en `parametros_fiscales`.
 *
 * Limitación documentada: no aplica los adicionales por edad ≥65/ciego
 * (Form 1040 línea 12d, `estado_civil` no captura esos hechos todavía).
 */
class StandardDeductionCalculator
{
    /**
     * @return array{disponible: bool, motivo_no_disponible: ?string, deduccion_estandar?: float, deduccion_itemizada?: float, deduccion_aplicable?: float, usa_itemizada?: bool}
     */
    public function calcular(int $taxYear, FilingStatus $filingStatus, float $deduccionItemizada): array
    {
        $clave = match ($filingStatus) {
            FilingStatus::MarriedFilingJointly, FilingStatus::QualifyingSurvivingSpouse => 'monto_mfj',
            FilingStatus::Single => 'monto_soltero',
            FilingStatus::HeadOfHousehold => 'monto_hoh',
        };

        $deduccionEstandar = (float) ParametrosFiscales::valorRequerido($taxYear, 'deduccion_estandar', $clave);
        $usaItemizada = $deduccionItemizada > $deduccionEstandar;

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'deduccion_estandar' => $deduccionEstandar,
            'deduccion_itemizada' => $deduccionItemizada,
            'deduccion_aplicable' => $usaItemizada ? $deduccionItemizada : $deduccionEstandar,
            'usa_itemizada' => $usaItemizada,
        ];
    }
}
