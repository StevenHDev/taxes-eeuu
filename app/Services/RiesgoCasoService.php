<?php

namespace App\Services;

use App\Enums\FieldState;
use App\Enums\FormState;
use App\Enums\NivelRiesgo;
use App\Models\CampoCliente;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\NivelRiesgoManual;
use App\Models\User;

/**
 * Nivel de riesgo de un caso (sección 5 del roadmap, "pendientes menores") —
 * híbrido: un preparador/administrador puede sobreescribirlo manualmente
 * (ver App\Models\NivelRiesgoManual), pero por default se sugiere uno
 * calculado con una heurística simple, documentada como tal — no es una
 * fórmula actuarial ni un requisito del IRS, es una ayuda de triage interno.
 */
class RiesgoCasoService
{
    /**
     * @return array{nivel: NivelRiesgo, fuente: 'manual'|'automatico'}
     */
    public function nivelEfectivo(User $cliente, int $taxYear): array
    {
        $manual = NivelRiesgoManual::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->first();

        if ($manual) {
            return ['nivel' => $manual->nivel, 'fuente' => 'manual'];
        }

        return ['nivel' => $this->nivelAutomatico($cliente, $taxYear), 'fuente' => 'automatico'];
    }

    /**
     * Heurística: alto si hay algo activamente inválido (requiere atención
     * inmediata); medio si el caso todavía está incompleto o es complejo;
     * bajo en cualquier otro caso. Deliberadamente simple — un conteo de
     * señales ya disponibles, no un modelo de riesgo real.
     */
    public function nivelAutomatico(User $cliente, int $taxYear): NivelRiesgo
    {
        $camposInvalidos = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->where('estado', FieldState::Invalido)
            ->exists();

        $documentosInvalidos = Documento::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->where('estado_validacion', FieldState::Invalido)
            ->exists();

        if ($camposInvalidos || $documentosInvalidos) {
            return NivelRiesgo::Alto;
        }

        $formasDelAno = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->get();

        $formasIncompletas = $formasDelAno->contains(fn (FormaCliente $f) => $f->estado !== FormState::Completo);

        $determinacionFaltante = $formasDelAno->isNotEmpty() && ! DeterminacionFiscal::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->exists();

        $complejo = $formasDelAno->count() >= 3;

        if ($formasIncompletas || $determinacionFaltante || $complejo) {
            return NivelRiesgo::Medio;
        }

        return NivelRiesgo::Bajo;
    }
}
