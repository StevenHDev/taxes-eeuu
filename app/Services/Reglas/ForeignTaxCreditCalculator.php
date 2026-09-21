<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;
use App\Support\ParametrosFiscales;

/**
 * Foreign Tax Credit simplificado — Fase 4 del plan de cierre de brecha GTS.
 * `impuesto_extranjero_pagado` (form_1040, ver CatalogoCamposSeeder) se
 * capturaba desde antes pero no alimentaba ningún crédito real.
 *
 * Solo implementa la elección "de minimis" sin Form 1116 (IRC §904(j)): si
 * el impuesto extranjero pagado no supera el umbral ($300 soltero/HOH/MFS,
 * $600 casados presentando juntos — QSS agrupado con MFJ, mismo criterio ya
 * usado en StandardDeductionCalculator para esa misma pareja de estados
 * civiles), el crédito es el monto pagado tal cual, sin aplicar ningún
 * límite de asignación.
 *
 * Limitación documentada — a propósito, no una omisión: por encima del
 * umbral, el crédito real requiere Form 1116 completo (categoría de
 * ingreso pasiva/general, límite = impuesto US × ingreso extranjero/ingreso
 * total, límite por país, carryback/carryforward de 1/10 años) — nada de
 * eso está modelado, porque el catálogo hoy solo captura un monto agregado
 * de impuesto extranjero pagado (`impuesto_extranjero_pagado`) sin país ni
 * categoría de ingreso. En ese caso el crédito queda en 0 con
 * `requiere_form_1116: true`, para que el preparador lo calcule aparte —
 * nunca se inventa ni se aproxima ese límite.
 */
class ForeignTaxCreditCalculator
{
    /**
     * @return array{disponible: true, motivo_no_disponible: null, credito: float, requiere_form_1116: bool}
     */
    public function calcular(int $taxYear, FilingStatus $filingStatus, float $impuestoExtranjeroPagado): array
    {
        if ($impuestoExtranjeroPagado <= 0.0) {
            return [
                'disponible' => true,
                'motivo_no_disponible' => null,
                'credito' => 0.0,
                'requiere_form_1116' => false,
            ];
        }

        $umbral = in_array($filingStatus, [FilingStatus::MarriedFilingJointly, FilingStatus::QualifyingSurvivingSpouse], true)
            ? (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_ftc', 'umbral_de_minimis_mfj')
            : (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_ftc', 'umbral_de_minimis_soltero');

        $requiereForm1116 = $impuestoExtranjeroPagado > $umbral;

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'credito' => $requiereForm1116 ? 0.0 : $impuestoExtranjeroPagado,
            'requiere_form_1116' => $requiereForm1116,
        ];
    }
}
