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
     * @return array{disponible: bool, motivo_no_disponible: ?string, agi?: float, ingreso_bruto_total?: float, ajustes?: float}
     */
    public function calcular(array $ingresos): array
    {
        $campos = ['salarios', 'intereses_dividendos', 'ganancias_capital', 'ingresos_jubilacion', 'otros_ingresos'];

        $ingresoBrutoTotal = 0.0;

        foreach ($campos as $campo) {
            $ingresoBrutoTotal += (float) ($ingresos[$campo] ?? 0);
        }

        $ajustes = (float) ($ingresos['ajustes_ingreso'] ?? 0);

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
