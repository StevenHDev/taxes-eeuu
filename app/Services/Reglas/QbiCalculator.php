<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;
use App\Support\ParametrosFiscales;

/**
 * Qualified Business Income Deduction (Form 8995, §199A) — versión
 * SIMPLIFICADA: 20% del QBI, limitado al 20% de (taxable income antes de QBI
 * menos ganancia de capital neta) — la regla del "menor de los dos" que sí
 * aplica siempre, sin excepción.
 *
 * Limitación documentada — NO implementa (por complejidad y por depender de
 * hechos que el catálogo no recolecta todavía): el phase-out de negocios
 * SSTB (servicios especializados) ni el límite de W-2 wages/UBIA de activos
 * calificados para contribuyentes por encima del umbral de ingreso. Si
 * `taxable_income_antes_qbi` supera el umbral (`requiere_revision_manual:
 * true`), el número que devuelve es solo un estimado de referencia — un
 * preparador debe recalcularlo con Form 8995-A antes de usarlo en una
 * declaración real.
 */
class QbiCalculator
{
    private const TASA = 0.20;

    /**
     * @return array{disponible: bool, motivo_no_disponible: ?string, qbi?: float, deduccion?: float, requiere_revision_manual?: bool}
     */
    public function calcular(int $taxYear, FilingStatus $filingStatus, float $qbi, float $taxableIncomeAntesQbi, float $gananciaCapitalNeta): array
    {
        if ($qbi <= 0.0) {
            return [
                'disponible' => true,
                'motivo_no_disponible' => null,
                'qbi' => max(0.0, $qbi),
                'deduccion' => 0.0,
                'requiere_revision_manual' => false,
            ];
        }

        $clave = $filingStatus === FilingStatus::MarriedFilingJointly ? 'umbral_mfj' : 'umbral_soltero';
        $umbral = (float) ParametrosFiscales::valorRequerido($taxYear, 'qbi', $clave);

        $limiteIngreso = max(0.0, $taxableIncomeAntesQbi - max(0.0, $gananciaCapitalNeta)) * self::TASA;
        $deduccionTentativa = $qbi * self::TASA;

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'qbi' => $qbi,
            'deduccion' => min($deduccionTentativa, $limiteIngreso),
            'requiere_revision_manual' => $taxableIncomeAntesQbi > $umbral,
        ];
    }
}
