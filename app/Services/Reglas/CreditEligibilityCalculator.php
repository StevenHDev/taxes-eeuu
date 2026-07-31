<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;
use App\Support\ParametrosFiscales;

/**
 * Calcula los créditos elegibles: Child Tax Credit + Credit for Other
 * Dependents (combinados en un solo phase-out — Form 8812 los suma ANTES de
 * reducir, no reduce cada uno por separado) y el Child and Dependent Care
 * Credit (Form 2441).
 *
 * Limitación documentada: el tope real de "earned income" por cónyuge del
 * crédito de cuidado de dependientes no se aplica — `ingresos.salarios` es un
 * solo número combinado, no desglosado por cónyuge.
 */
class CreditEligibilityCalculator
{
    /**
     * @param  array<string, mixed>  $dependientesResultado  resultado de DependentQualificationCalculator
     * @param  array<string, mixed>|null  $gastosCuidado
     * @return array{disponible: bool, motivo_no_disponible: ?string, ctc?: float, odc?: float, cuidado_dependientes?: float, reduccion_por_agi?: float, total?: float}
     */
    public function calcular(int $taxYear, FilingStatus $filingStatus, float $agi, array $dependientesResultado, ?array $gastosCuidado): array
    {
        [$ctc, $odc, $reduccion] = $this->calcularCtcYOdc($taxYear, $filingStatus, $agi, $dependientesResultado);
        $cuidado = $this->calcularCuidadoDependientes($taxYear, $agi, $dependientesResultado, $gastosCuidado);

        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'ctc' => $ctc,
            'odc' => $odc,
            'reduccion_por_agi' => $reduccion,
            'cuidado_dependientes' => $cuidado,
            'total' => $ctc + $odc + $cuidado,
        ];
    }

    /**
     * @param  array<string, mixed>  $dependientesResultado
     * @return array{0: float, 1: float, 2: float}
     */
    private function calcularCtcYOdc(int $taxYear, FilingStatus $filingStatus, float $agi, array $dependientesResultado): array
    {
        $montoCtc = (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_ctc', 'monto_por_dependiente');
        $montoOdc = (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_odc', 'monto_por_dependiente');
        $tasaReduccion = (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_ctc', 'reduccion_por_1000_exceso');

        $umbral = $filingStatus === FilingStatus::MarriedFilingJointly
            ? (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_ctc', 'phase_out_umbral_mfj')
            : (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_ctc', 'phase_out_umbral_soltero');

        $tentativoCtc = $dependientesResultado['conteo_ctc'] * $montoCtc;
        $tentativoOdc = $dependientesResultado['conteo_odc'] * $montoOdc;
        $tentativoTotal = $tentativoCtc + $tentativoOdc;

        $exceso = max(0.0, $agi - $umbral);
        // Redondeo hacia ARRIBA: $1 de exceso ya cuesta la reducción completa
        // de $1,000 — floor() dejaría escapar a alguien $999 por encima del
        // umbral sin ninguna reducción.
        $reduccion = ceil($exceso / 1000) * $tasaReduccion;

        $totalCombinado = max(0.0, $tentativoTotal - $reduccion);

        if ($tentativoTotal <= 0.0) {
            return [0.0, 0.0, 0.0];
        }

        // El total combinado ya tiene la reducción aplicada correctamente;
        // se reparte proporcionalmente entre CTC/ODC solo para mostrarlo
        // desglosado — la suma sigue siendo exacta.
        $ctc = $totalCombinado * ($tentativoCtc / $tentativoTotal);
        $odc = $totalCombinado * ($tentativoOdc / $tentativoTotal);

        return [$ctc, $odc, $reduccion];
    }

    /**
     * @param  array<string, mixed>  $dependientesResultado
     * @param  array<string, mixed>|null  $gastosCuidado
     */
    private function calcularCuidadoDependientes(int $taxYear, float $agi, array $dependientesResultado, ?array $gastosCuidado): float
    {
        if ($gastosCuidado === null || ! isset($gastosCuidado['monto_anual'])) {
            return 0.0;
        }

        $personasCalificadas = $dependientesResultado['conteo_cuidado'];

        if ($personasCalificadas <= 0) {
            return 0.0;
        }

        $tope = $personasCalificadas >= 2
            ? (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_cuidado_dependientes', 'tope_gastos_2_mas_personas')
            : (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_cuidado_dependientes', 'tope_gastos_1_persona');

        $gastosElegibles = min((float) $gastosCuidado['monto_anual'], $tope);

        $porcentaje = $this->porcentajeCuidado($taxYear, $agi);

        return $gastosElegibles * $porcentaje;
    }

    /**
     * Interpolación LINEAL entre los dos extremos publicados por el IRS
     * (35% a AGI <= $15k, 20% a AGI >= $43k) — simplificación documentada de
     * la tabla real, que baja 1% cada $2,000 de forma escalonada, no lineal.
     * Reemplazar por la tabla exacta antes de usar en producción real.
     */
    private function porcentajeCuidado(int $taxYear, float $agi): float
    {
        $porcentajeMaximo = (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_cuidado_dependientes', 'porcentaje_maximo');
        $porcentajeMinimo = (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_cuidado_dependientes', 'porcentaje_minimo');
        $umbralMaximo = (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_cuidado_dependientes', 'agi_umbral_porcentaje_maximo');
        $umbralMinimo = (float) ParametrosFiscales::valorRequerido($taxYear, 'credito_cuidado_dependientes', 'agi_umbral_porcentaje_minimo');

        if ($agi <= $umbralMaximo) {
            return $porcentajeMaximo;
        }

        if ($agi >= $umbralMinimo) {
            return $porcentajeMinimo;
        }

        $proporcion = ($agi - $umbralMaximo) / ($umbralMinimo - $umbralMaximo);

        return $porcentajeMaximo - $proporcion * ($porcentajeMaximo - $porcentajeMinimo);
    }
}
