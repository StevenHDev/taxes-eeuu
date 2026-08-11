<?php

namespace App\Services\Reglas;

/**
 * Calcula el Adjusted Gross Income a partir del `ingresos` desglosado de
 * form_1040. Suma bruta menos ajustes.
 */
class AgiCalculator
{
    /**
     * @param  array<string, mixed>  $ingresos
     * @param  float  $ajustesAdicionales  ajustes calculados que no vienen del
     *                                     campo `ingresos.ajustes_ingreso` que
     *                                     el cliente reportó a mano — hoy solo
     *                                     la mitad deducible del SE tax (ver
     *                                     SelfEmploymentTaxCalculator); es la
     *                                     misma categoría de "Adjustments to
     *                                     Income" del Schedule 1 Parte II,
     *                                     solo que este componente lo calcula
     *                                     el motor, no lo escribe el cliente.
     * @param  float  $ingresoNegocioRentaGranja  ingreso neto (no bruto) de
     *                                            schedule_c + schedule_e +
     *                                            schedule_f (Schedule 1 líneas
     *                                            3/5/6 → 1040 línea 8) — Fase
     *                                            6. Puede ser negativo (una
     *                                            pérdida de negocio SÍ reduce
     *                                            el AGI). Antes de la Fase 6
     *                                            este ingreso se calculaba
     *                                            para SE tax/QBI/NIIT pero
     *                                            nunca llegaba al AGI — bug
     *                                            real encontrado en pruebas
     *                                            end-to-end, corregido acá.
     * @return array{disponible: bool, motivo_no_disponible: ?string, agi?: float, ingreso_bruto_total?: float, ajustes?: float}
     */
    public function calcular(array $ingresos, float $ajustesAdicionales = 0.0, float $ingresoNegocioRentaGranja = 0.0): array
    {
        // 'seguridad_social' (Fase 6) se excluye del ingreso bruto total
        // directo a propósito: su porción gravable depende del "provisional
        // income" (ingreso combinado) vía el Social Security Benefits
        // Worksheet, no es 100% gravable como los demás — limitación
        // documentada: hoy este AGI trata la seguridad social reportada como
        // no gravable (0%), el caso más conservador posible, hasta que exista
        // una calculadora dedicada para esa porción.
        $campos = ['salarios', 'intereses_dividendos', 'ganancias_capital', 'ingresos_jubilacion', 'otros_ingresos'];

        $ingresoBrutoTotal = $ingresoNegocioRentaGranja;

        foreach ($campos as $campo) {
            $ingresoBrutoTotal += (float) ($ingresos[$campo] ?? 0);
        }

        $ajustes = (float) ($ingresos['ajustes_ingreso'] ?? 0) + $ajustesAdicionales;

        // El AGI no se limita a 0: una pérdida de capital grande puede dejarlo
        // negativo legítimamente — decisión explícita, no un olvido.
        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'agi' => $ingresoBrutoTotal - $ajustes,
            'ingreso_bruto_total' => $ingresoBrutoTotal,
            'ajustes' => $ajustes,
        ];
    }
}
