<?php

namespace App\Services;

use App\Enums\FieldState;
use App\Enums\FilingStatus;
use App\Enums\TipoDeterminacion;
use App\Models\CampoCliente;
use App\Models\DeterminacionFiscal;
use App\Models\User;
use App\Services\Reglas\AgiCalculator;
use App\Services\Reglas\CreditEligibilityCalculator;
use App\Services\Reglas\DependentQualificationCalculator;
use App\Services\Reglas\FilingStatusCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta las cuatro calculadoras del motor de reglas para un cliente y un
 * año fiscal, y persiste el resultado en `determinaciones_fiscales` — nunca
 * en `campos_cliente` (esa tabla es solo para lo que el cliente/agente
 * entregó, ver docs/plan-desarrollo-fases.md Decisión B).
 */
class DeterminacionFiscalService
{
    /**
     * Identifica qué versión de reglas/parámetros produjo un resultado, para
     * poder auditar "por qué salió este número" más adelante. Coincide con el
     * año base de parametros_fiscales hoy; puede diverger si la ley cambia a
     * mitad de año y hay que versionar reglas dentro del mismo tax_year.
     */
    private const VERSION_REGLAS = '2025.1';

    public function __construct(
        private readonly DependentQualificationCalculator $dependientes,
        private readonly FilingStatusCalculator $filingStatus,
        private readonly AgiCalculator $agi,
        private readonly CreditEligibilityCalculator $creditos,
    ) {}

    /**
     * @return array<string, array<string, mixed>> las 4 determinaciones, indexadas por TipoDeterminacion
     */
    public function calcularPara(User $cliente, int $taxYear): array
    {
        return DB::transaction(function () use ($cliente, $taxYear) {
            $estadoCivil = $this->leerValor($cliente, $taxYear, 'transversal', 'estado_civil');
            $dependientesRaw = $this->leerValor($cliente, $taxYear, 'transversal', 'info_dependientes');
            $ingresos = $this->leerValor($cliente, $taxYear, 'form_1040', 'ingresos');
            $gastosCuidado = $this->leerValor($cliente, $taxYear, 'form_1040', 'gastos_cuidado_dependientes');

            // is_array(), no solo !== null: un campo cargado ANTES de que el
            // catálogo cambiara de shape en la Fase 2 (ej. `ingresos` como
            // number suelto) sigue teniendo un valor no-null en la base, pero
            // ya no tiene la forma que la calculadora espera — sin este
            // chequeo, se le pasaría un escalar a una calculadora que espera
            // un array y tronaría con un TypeError (500), en vez de mostrar
            // "no disponible" con un motivo claro para que el preparador
            // vuelva a cargar el campo.
            $dependientes = is_array($dependientesRaw)
                ? $this->dependientes->calcular($taxYear, $dependientesRaw)
                : $this->noDisponible($dependientesRaw === null
                    ? 'info_dependientes no ha sido capturado todavía'
                    : 'info_dependientes tiene un formato inesperado — vuelve a cargarlo');

            // Los dependientes se calculan primero: su resultado alimenta el
            // filing status (existe qualifying child / algún calificado), en
            // vez de confiar ciegamente en el flag auto-reportado del agente.
            $filingStatus = is_array($estadoCivil)
                ? $this->filingStatus->calcular(
                    $taxYear,
                    $estadoCivil,
                    $dependientes['disponible'] && $dependientes['conteo_qualifying_child'] > 0,
                    $dependientes['disponible'] && ($dependientes['conteo_qualifying_child'] + $dependientes['conteo_qualifying_relative']) > 0,
                )
                : $this->noDisponible($estadoCivil === null
                    ? 'estado_civil no ha sido capturado todavía'
                    : 'estado_civil tiene un formato inesperado — vuelve a cargarlo');

            $agi = is_array($ingresos)
                ? $this->agi->calcular($ingresos)
                : $this->noDisponible($ingresos === null
                    ? 'ingresos no ha sido capturado todavía'
                    : 'ingresos tiene un formato antiguo (cargado antes de la Fase 2) — vuelve a cargarlo con el formulario actual para poder calcular el AGI');

            // gastos_cuidado_dependientes es opcional: un valor no-array (viejo
            // o corrupto) se trata como "sin gastos reportados", no como error.
            $gastosCuidado = is_array($gastosCuidado) ? $gastosCuidado : null;

            $creditos = ($filingStatus['disponible'] && $agi['disponible'] && $dependientes['disponible'])
                ? $this->creditos->calcular($taxYear, FilingStatus::from($filingStatus['estado']), $agi['agi'], $dependientes, $gastosCuidado)
                : $this->noDisponible('depende de estado_civil, ingresos e info_dependientes');

            $resultados = [
                TipoDeterminacion::Dependientes->value => $dependientes,
                TipoDeterminacion::FilingStatus->value => $filingStatus,
                TipoDeterminacion::Agi->value => $agi,
                TipoDeterminacion::Creditos->value => $creditos,
            ];

            foreach ($resultados as $tipo => $resultado) {
                DeterminacionFiscal::query()->updateOrCreate(
                    ['user_id' => $cliente->id, 'tax_year' => $taxYear, 'tipo' => $tipo],
                    ['resultado' => $resultado, 'version_reglas' => self::VERSION_REGLAS, 'calculado_en' => now()],
                );
            }

            return $resultados;
        });
    }

    /**
     * Lee el valor crudo (decrypted) de un campo del cliente para ese año.
     * SIEMPRE `valor_texto`, NUNCA el accessor `->valor` (enmascara campos
     * sensibles para mostrar en UI — alimentar eso a una calculadora
     * produciría resultados basados en datos enmascarados). Solo cuenta un
     * campo en estado `Recibido`: una fila `invalido` no es dato utilizable.
     */
    private function leerValor(User $cliente, int $taxYear, string $forma, string $campo): mixed
    {
        // ->first()->valor_texto (no ->value('valor_texto')): value() sobre un
        // query builder no pasa por la hidratación completa del modelo, así
        // que el cast `encrypted:array` no se aplicaría de forma confiable —
        // ->first() sí hidrata el modelo, garantizando que llega desencriptado.
        return CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $forma)
            ->where('campo', $campo)
            ->where('tax_year', $taxYear)
            ->where('estado', FieldState::Recibido)
            ->first()
            ?->valor_texto;
    }

    /**
     * @return array{disponible: bool, motivo_no_disponible: string}
     */
    private function noDisponible(string $motivo): array
    {
        return ['disponible' => false, 'motivo_no_disponible' => $motivo];
    }
}
