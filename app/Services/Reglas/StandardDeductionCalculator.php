<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;
use App\Support\ParametrosFiscales;

/**
 * Compara la deducción estándar (Form 1040 línea 12a-12c) contra la
 * deducción itemizada que el cliente ya reportó en `form_1040.deducciones`
 * (Schedule A, desglosado por categoría desde la Fase 4 del plan de cierre
 * de brecha GTS — hipoteca, impuestos de propiedad, donaciones en efectivo,
 * donaciones en bienes, gastos médicos, intereses de inversión, pérdidas
 * por desastre) y devuelve la mayor — el contribuyente usa la que más le
 * conviene, nunca las dos.
 *
 * QSS usa la misma tabla que MFJ (regla del IRC, no una cifra que cambie por
 * año) — por eso no tiene su propia clave en `parametros_fiscales`.
 *
 * Limitaciones documentadas: no aplica los adicionales por edad ≥65/ciego
 * (Form 1040 línea 12d, `estado_civil` no captura esos hechos todavía), ni
 * el piso de 7.5% del AGI sobre gastos médicos, ni los límites de
 * donaciones, ni el requisito de zona de desastre declarada para pérdidas
 * por desastre — suma los hechos crudos tal cual el cliente los reportó,
 * no aplica esas reglas del IRC.
 */
class StandardDeductionCalculator
{
    /**
     * @param  array<string, mixed>|null  $deducciones  subcampos de form_1040.deducciones
     *                                                  (ver CatalogoCamposSeeder) — null o no-array
     *                                                  si el cliente no cargó nada, o si el valor
     *                                                  guardado todavía tiene el shape viejo (Number
     *                                                  suelto, antes de la Fase 4): se trata como 0,
     *                                                  nunca truena.
     * @return array{disponible: true, motivo_no_disponible: null, deduccion_estandar: float, deduccion_itemizada: float, deduccion_aplicable: float, usa_itemizada: bool}
     */
    public function calcular(int $taxYear, FilingStatus $filingStatus, ?array $deducciones): array
    {
        $clave = match ($filingStatus) {
            FilingStatus::MarriedFilingJointly, FilingStatus::QualifyingSurvivingSpouse => 'monto_mfj',
            FilingStatus::Single => 'monto_soltero',
            FilingStatus::HeadOfHousehold => 'monto_hoh',
        };

        $deduccionEstandar = (float) ParametrosFiscales::valorRequerido($taxYear, 'deduccion_estandar', $clave);
        $deduccionItemizada = $this->sumarSubcampos($deducciones);
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

    /**
     * @param  array<string, mixed>|null  $deducciones
     */
    private function sumarSubcampos(?array $deducciones): float
    {
        if ($deducciones === null) {
            return 0.0;
        }

        return array_sum(array_map(
            fn (mixed $valor) => is_numeric($valor) ? (float) $valor : 0.0,
            $deducciones,
        ));
    }
}
