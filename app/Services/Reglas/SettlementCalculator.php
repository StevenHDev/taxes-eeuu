<?php

namespace App\Services\Reglas;

/**
 * Liquidación final (Form 1040 líneas 22-37): junta el impuesto sobre el
 * ingreso ya reducido por créditos no reembolsables (línea 22), le suma los
 * "otros impuestos" de Schedule 2 Parte II (SE tax + Additional Medicare Tax
 * + NIIT — estos NO se reducen por créditos, se agregan después), y compara
 * el total contra los pagos/retenciones para determinar reembolso o saldo a
 * pagar.
 *
 * Limitación documentada: `total_pagos` hoy solo suma `impuestos_retenidos`
 * (W-2/1099) — no hay campo en el catálogo todavía para pagos estimados
 * (Form 1040-ES) ni para créditos reembolsables (EIC, Additional CTC,
 * porción reembolsable del AOTC), así que el resultado subestima los pagos
 * de cualquier cliente que hizo pagos estimados o califica para esos créditos.
 */
class SettlementCalculator
{
    /**
     * @return array{disponible: bool, motivo_no_disponible: ?string, impuesto_antes_otros?: float, otros_impuestos?: float, total_impuesto?: float, total_pagos?: float, reembolso?: float, saldo_a_pagar?: float}
     */
    public function calcular(
        float $impuestoSobreIngreso,
        float $creditosNoReembolsables,
        float $impuestoSe,
        float $impuestoMedicareAdicional,
        float $impuestoNiit,
        float $totalPagos,
    ): array {
        $impuestoAntesOtros = max(0.0, $impuestoSobreIngreso - $creditosNoReembolsables);
        $otrosImpuestos = $impuestoSe + $impuestoMedicareAdicional + $impuestoNiit;
        $totalImpuesto = $impuestoAntesOtros + $otrosImpuestos;

        $sobrepago = max(0.0, $totalPagos - $totalImpuesto);
        $saldoAPagar = max(0.0, $totalImpuesto - $totalPagos);

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'impuesto_antes_otros' => $impuestoAntesOtros,
            'otros_impuestos' => $otrosImpuestos,
            'total_impuesto' => $totalImpuesto,
            'total_pagos' => $totalPagos,
            'reembolso' => $sobrepago,
            'saldo_a_pagar' => $saldoAPagar,
        ];
    }
}
