<?php

namespace App\Services\Reglas;

use App\Support\ParametrosFiscales;
use Carbon\Carbon;

/**
 * Determina si cada dependiente califica como "qualifying child" o
 * "qualifying relative", y qué créditos habilita. Corre ANTES que
 * FilingStatusCalculator: su resultado (si existe un qualifying child /
 * algún dependiente calificado) alimenta la determinación de filing status,
 * en vez de confiar en el flag auto-reportado `estado_civil.existe_persona_calificable`
 * — evita que el agente conversacional y el cálculo real queden inconsistentes.
 */
class DependentQualificationCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $dependientes
     * @return array{
     *     disponible: bool,
     *     motivo_no_disponible: ?string,
     *     dependientes?: array<int, array<string, mixed>>,
     *     conteo_qualifying_child?: int,
     *     conteo_qualifying_relative?: int,
     *     conteo_ctc?: int,
     *     conteo_odc?: int,
     *     conteo_cuidado?: int,
     * }
     */
    public function calcular(int $taxYear, array $dependientes): array
    {
        $finDeAnio = Carbon::create($taxYear, 12, 31);
        $limiteIngresoQr = ParametrosFiscales::valorRequerido($taxYear, 'dependiente_calificado', 'limite_ingreso_bruto_pariente_calificado');

        $resultado = [];
        $conteoQc = 0;
        $conteoQr = 0;
        $conteoCtc = 0;
        $conteoOdc = 0;
        $conteoCuidado = 0;

        foreach ($dependientes as $dependiente) {
            $edad = $this->edadAlFinDeAnio($dependiente['fecha_nacimiento'] ?? null, $finDeAnio);
            $discapacitado = (bool) ($dependiente['discapacitado'] ?? false);
            $proveePropioSoporte = (bool) ($dependiente['provee_mas_50_soporte_propio'] ?? false);

            $esQualifyingChild = ! $proveePropioSoporte
                && $this->relacionEsDeHijo((string) ($dependiente['relacion'] ?? ''))
                && (int) ($dependiente['meses_en_hogar'] ?? 0) >= 6
                && $edad !== null
                && ($edad < 19 || ($edad < 24 && ($dependiente['estudiante_tiempo_completo'] ?? false)) || $discapacitado);

            $esQualifyingRelative = ! $esQualifyingChild
                && ! $proveePropioSoporte
                && is_numeric($dependiente['ingreso_bruto_anual'] ?? null)
                && (float) $dependiente['ingreso_bruto_anual'] <= $limiteIngresoQr;

            $calificacion = $esQualifyingChild ? 'qualifying_child' : ($esQualifyingRelative ? 'qualifying_relative' : 'ninguna');

            // CTC exige qualifying child Y menor de 17 al cierre del año — un
            // qualifying child de 17-18 años solo habilita ODC, no CTC.
            $elegibleCtc = $esQualifyingChild && $edad !== null && $edad < 17;
            $elegibleOdc = ($esQualifyingChild || $esQualifyingRelative) && ! $elegibleCtc;
            // Tope de Form 2441: cuenta cualquier dependiente calificado menor
            // de la edad límite o discapacitado, sin cruzar contra
            // `dependiente_relacionado` de gastos_cuidado_dependientes (ver
            // limitación documentada en CreditEligibilityCalculator).
            $elegibleCuidado = ($esQualifyingChild || $esQualifyingRelative)
                && ($discapacitado || ($edad !== null && $edad < (int) ParametrosFiscales::valorRequerido($taxYear, 'credito_cuidado_dependientes', 'edad_limite_dependiente')));

            if ($esQualifyingChild) {
                $conteoQc++;
            } elseif ($esQualifyingRelative) {
                $conteoQr++;
            }

            if ($elegibleCtc) {
                $conteoCtc++;
            } elseif ($elegibleOdc) {
                $conteoOdc++;
            }

            if ($elegibleCuidado) {
                $conteoCuidado++;
            }

            $resultado[] = [
                'nombre_completo' => $dependiente['nombre_completo'] ?? null,
                'calificacion' => $calificacion,
                'edad_fin_anio' => $edad,
                'elegible_ctc' => $elegibleCtc,
                'elegible_odc' => $elegibleOdc,
                'elegible_cuidado' => $elegibleCuidado,
            ];
        }

        // custodia_compartida_sin_conflicto: no-op a propósito en esta fase —
        // no hay todavía lógica de "quién reclama al dependiente" entre padres
        // separados (Form 8332); el dato se captura para uso futuro.
        return [
            'disponible' => true,
            'motivo_no_disponible' => null,
            'dependientes' => $resultado,
            'conteo_qualifying_child' => $conteoQc,
            'conteo_qualifying_relative' => $conteoQr,
            'conteo_ctc' => $conteoCtc,
            'conteo_odc' => $conteoOdc,
            'conteo_cuidado' => $conteoCuidado,
        ];
    }

    private function edadAlFinDeAnio(?string $fechaNacimiento, Carbon $finDeAnio): ?int
    {
        if (! $fechaNacimiento) {
            return null;
        }

        try {
            return Carbon::parse($fechaNacimiento)->diffInYears($finDeAnio);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Relaciones que califican para el test de "qualifying child" (hijo,
     * hijastro, hermano, o descendiente directo de estos). No exhaustivo del
     * todo el código del IRC, pero cubre los casos comunes de la guía.
     */
    private function relacionEsDeHijo(string $relacion): bool
    {
        return in_array(mb_strtolower($relacion), [
            'hijo', 'hija', 'hijastro', 'hijastra', 'hermano', 'hermana',
            'hermanastro', 'hermanastra', 'nieto', 'nieta', 'sobrino', 'sobrina',
        ], true);
    }
}
