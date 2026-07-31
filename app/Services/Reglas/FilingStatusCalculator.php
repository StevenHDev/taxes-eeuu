<?php

namespace App\Services\Reglas;

use App\Enums\FilingStatus;

/**
 * Determina el filing status a partir de los HECHOS en `estado_civil` (nunca
 * se le pregunta la conclusión al cliente). MFS (Married Filing Separately)
 * no es alcanzable por este cálculo: es una elección del contribuyente, no
 * derivable de estos hechos — limitación documentada, no un bug.
 */
class FilingStatusCalculator
{
    /**
     * @param  array<string, mixed>  $estadoCivil
     * @return array{disponible: bool, motivo_no_disponible: ?string, estado?: string}
     */
    public function calcular(int $taxYear, array $estadoCivil, bool $existeQualifyingChild, bool $existeAlgunDependienteCalificado): array
    {
        if ($estadoCivil['casado_al_31_dic'] ?? false) {
            return $this->resultado(FilingStatus::MarriedFilingJointly);
        }

        $conyugeFallecio = (bool) ($estadoCivil['conyuge_fallecio_en_anio'] ?? false);
        $anioFallecimiento = $estadoCivil['anio_fallecimiento_conyuge'] ?? null;
        $costeoMasMitadHogar = (bool) ($estadoCivil['costeo_mas_mitad_hogar'] ?? false);

        if ($conyugeFallecio && is_numeric($anioFallecimiento)) {
            $delta = $taxYear - (int) $anioFallecimiento;

            // El año de la muerte todavía se declara MFJ (si no hubo nuevas
            // nupcias) — QSS solo aplica a los DOS años siguientes al deceso.
            if ($delta === 0) {
                return $this->resultado(FilingStatus::MarriedFilingJointly);
            }

            if ($delta >= 1 && $delta <= 2 && $existeQualifyingChild && $costeoMasMitadHogar) {
                return $this->resultado(FilingStatus::QualifyingSurvivingSpouse);
            }
        }

        if ($existeAlgunDependienteCalificado && $costeoMasMitadHogar) {
            return $this->resultado(FilingStatus::HeadOfHousehold);
        }

        return $this->resultado(FilingStatus::Single);
    }

    /**
     * @return array{disponible: bool, motivo_no_disponible: ?string, estado: string}
     */
    private function resultado(FilingStatus $estado): array
    {
        return ['disponible' => true, 'motivo_no_disponible' => null, 'estado' => $estado->value];
    }
}
