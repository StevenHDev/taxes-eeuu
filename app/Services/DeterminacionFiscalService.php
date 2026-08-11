<?php

namespace App\Services;

use App\Enums\FieldState;
use App\Enums\FilingStatus;
use App\Enums\TipoDeterminacion;
use App\Models\CampoCliente;
use App\Models\DeterminacionFiscal;
use App\Models\User;
use App\Services\Reglas\AdditionalMedicareTaxCalculator;
use App\Services\Reglas\AgiCalculator;
use App\Services\Reglas\CreditEligibilityCalculator;
use App\Services\Reglas\DependentQualificationCalculator;
use App\Services\Reglas\FilingStatusCalculator;
use App\Services\Reglas\NiitCalculator;
use App\Services\Reglas\QbiCalculator;
use App\Services\Reglas\SelfEmploymentTaxCalculator;
use App\Services\Reglas\SettlementCalculator;
use App\Services\Reglas\StandardDeductionCalculator;
use App\Services\Reglas\TaxableIncomeAndTaxCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta las once calculadoras del motor de reglas para un cliente y un año
 * fiscal, y persiste el resultado en `determinaciones_fiscales` — nunca en
 * `campos_cliente` (esa tabla es solo para lo que el cliente/agente entregó,
 * ver docs/plan-desarrollo-fases.md Decisión B).
 *
 * Orden de cálculo (cada paso alimenta al siguiente, replicando la secuencia
 * real del Form 1040): dependientes → filing status → SE tax (necesario
 * ANTES del AGI, porque la mitad es deducible como ajuste) → AGI → deducción
 * aplicable (estándar vs itemizada) → QBI (necesita AGI y la deducción ya
 * resueltas) → impuesto sobre el ingreso → créditos no reembolsables →
 * Additional Medicare Tax / NIIT (otros impuestos de Schedule 2 Parte II) →
 * liquidación final (reembolso o saldo a pagar).
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
        private readonly StandardDeductionCalculator $deduccionAplicable,
        private readonly SelfEmploymentTaxCalculator $impuestoAutoempleo,
        private readonly AdditionalMedicareTaxCalculator $impuestoMedicareAdicional,
        private readonly NiitCalculator $niit,
        private readonly QbiCalculator $qbi,
        private readonly TaxableIncomeAndTaxCalculator $impuestoIngreso,
        private readonly SettlementCalculator $liquidacion,
    ) {}

    /**
     * @return array<string, array<string, mixed>> las determinaciones, indexadas por TipoDeterminacion
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

            // --- Self-employment (Schedule C + Schedule F) — ANTES del AGI: la
            // mitad del SE tax es un ajuste al ingreso (Schedule 1 línea 15).
            // Una pérdida de un negocio SÍ compensa la ganancia del otro para
            // este cálculo (no se floorea cada uno en 0 por separado).
            $netoScheduleC = $this->leerNumero($cliente, $taxYear, 'schedule_c', 'ingresos_negocio')
                - $this->leerNumero($cliente, $taxYear, 'schedule_c', 'gastos_deducibles_negocio')
                - $this->leerNumero($cliente, $taxYear, 'schedule_c', 'costo_ventas');
            $netoScheduleF = $this->leerNumero($cliente, $taxYear, 'schedule_f', 'ventas_agricolas')
                + $this->leerNumero($cliente, $taxYear, 'schedule_f', 'subsidios')
                - $this->leerNumero($cliente, $taxYear, 'schedule_f', 'gastos_operacion');
            $netoAutoempleo = $netoScheduleC + $netoScheduleF;

            // Schedule E (alquiler) — neto, no bruto: se resta lo mismo que el
            // cliente ya reportó como gasto/depreciación de esa propiedad.
            // Se calcula acá (antes del AGI) porque Schedule 1 línea 5 suma
            // este neto al ingreso total — NIIT lo reutiliza más abajo, no lo
            // vuelve a leer en bruto.
            $netoScheduleE = $this->leerNumero($cliente, $taxYear, 'schedule_e', 'ingresos_renta')
                - $this->leerNumero($cliente, $taxYear, 'schedule_e', 'gastos_propiedad')
                - $this->leerNumero($cliente, $taxYear, 'schedule_e', 'depreciacion');

            $impuestoAutoempleo = $this->impuestoAutoempleo->calcular($taxYear, $netoAutoempleo);

            // Schedule 1 líneas 3/5/6 → 1040 línea 8 → AGI. Antes de la Fase 6
            // este ingreso se calculaba para SE tax/QBI/NIIT pero nunca llegaba
            // al AGI — bug real encontrado en pruebas end-to-end, corregido acá.
            $agi = is_array($ingresos)
                ? $this->agi->calcular($ingresos, $impuestoAutoempleo['mitad_deducible'], $netoAutoempleo + $netoScheduleE)
                : $this->noDisponible($ingresos === null
                    ? 'ingresos no ha sido capturado todavía'
                    : 'ingresos tiene un formato antiguo (cargado antes de la Fase 2) — vuelve a cargarlo con el formulario actual para poder calcular el AGI');

            // gastos_cuidado_dependientes es opcional: un valor no-array (viejo
            // o corrupto) se trata como "sin gastos reportados", no como error.
            $gastosCuidado = is_array($gastosCuidado) ? $gastosCuidado : null;

            $creditos = ($filingStatus['disponible'] && $agi['disponible'] && $dependientes['disponible'])
                ? $this->creditos->calcular($taxYear, FilingStatus::from($filingStatus['estado']), $agi['agi'], $dependientes, $gastosCuidado)
                : $this->noDisponible('depende de estado_civil, ingresos e info_dependientes');

            // --- Deducción aplicable (estándar vs itemizada) ---
            $deduccionItemizada = $this->leerNumero($cliente, $taxYear, 'form_1040', 'deducciones');
            $deduccionAplicable = ($filingStatus['disponible'])
                ? $this->deduccionAplicable->calcular($taxYear, FilingStatus::from($filingStatus['estado']), $deduccionItemizada)
                : $this->noDisponible('depende de estado_civil');

            // --- QBI (Form 8995 simplificado) — necesita AGI y la deducción ya
            // resueltas para el tope de "20% del taxable income antes de QBI".
            $qbiInput = max(0.0, $netoScheduleC) + max(0.0, $netoScheduleF);
            $gananciaCapitalNeta = is_array($ingresos) ? (float) ($ingresos['ganancias_capital'] ?? 0) : 0.0;
            $qbi = ($filingStatus['disponible'] && $agi['disponible'] && $deduccionAplicable['disponible'])
                ? $this->qbi->calcular(
                    $taxYear,
                    FilingStatus::from($filingStatus['estado']),
                    $qbiInput,
                    max(0.0, $agi['agi'] - $deduccionAplicable['deduccion_aplicable']),
                    $gananciaCapitalNeta,
                )
                : $this->noDisponible('depende de estado_civil, ingresos y deducciones');

            // --- Impuesto sobre el ingreso gravable (línea 15-16) ---
            $impuestoIngreso = ($filingStatus['disponible'] && $agi['disponible'] && $deduccionAplicable['disponible'] && $qbi['disponible'])
                ? $this->impuestoIngreso->calcular(
                    $taxYear,
                    FilingStatus::from($filingStatus['estado']),
                    $agi['agi'],
                    $deduccionAplicable['deduccion_aplicable'],
                    $qbi['deduccion'],
                )
                : $this->noDisponible('depende de estado_civil, ingresos, deducciones y QBI');

            // --- Additional Medicare Tax (Form 8959) ---
            $salariosMedicare = is_array($ingresos) ? (float) ($ingresos['salarios'] ?? 0) : 0.0;
            $impuestoMedicareAdicional = $filingStatus['disponible']
                ? $this->impuestoMedicareAdicional->calcular(
                    $taxYear,
                    FilingStatus::from($filingStatus['estado']),
                    $salariosMedicare,
                    $impuestoAutoempleo['base_gravable'],
                )
                : $this->noDisponible('depende de estado_civil');

            // --- NIIT (Form 8960) — reutiliza el neto de schedule_e ya
            // calculado arriba para el AGI, no lo vuelve a leer en bruto.
            $netoInversion = max(0.0, (is_array($ingresos)
                ? (float) ($ingresos['intereses_dividendos'] ?? 0) + (float) ($ingresos['ganancias_capital'] ?? 0)
                : 0.0) + $netoScheduleE);
            $niit = ($filingStatus['disponible'] && $agi['disponible'])
                ? $this->niit->calcular($taxYear, FilingStatus::from($filingStatus['estado']), $agi['agi'], $netoInversion)
                : $this->noDisponible('depende de estado_civil e ingresos');

            // --- Liquidación final (líneas 22-37) ---
            $impuestosRetenidos = $this->leerNumero($cliente, $taxYear, 'form_1040', 'impuestos_retenidos');
            $liquidacion = ($impuestoIngreso['disponible'] && $creditos['disponible'] && $impuestoMedicareAdicional['disponible'] && $niit['disponible'])
                ? $this->liquidacion->calcular(
                    $impuestoIngreso['impuesto'],
                    $creditos['total'],
                    $impuestoAutoempleo['impuesto_se'],
                    $impuestoMedicareAdicional['impuesto'],
                    $niit['impuesto'],
                    $impuestosRetenidos,
                )
                : $this->noDisponible('depende del impuesto sobre el ingreso, créditos, Additional Medicare Tax y NIIT');

            $resultados = [
                TipoDeterminacion::Dependientes->value => $dependientes,
                TipoDeterminacion::FilingStatus->value => $filingStatus,
                TipoDeterminacion::Agi->value => $agi,
                TipoDeterminacion::Creditos->value => $creditos,
                TipoDeterminacion::DeduccionAplicable->value => $deduccionAplicable,
                TipoDeterminacion::Qbi->value => $qbi,
                TipoDeterminacion::ImpuestoIngreso->value => $impuestoIngreso,
                TipoDeterminacion::ImpuestoAutoempleo->value => $impuestoAutoempleo,
                TipoDeterminacion::ImpuestoMedicareAdicional->value => $impuestoMedicareAdicional,
                TipoDeterminacion::Niit->value => $niit,
                TipoDeterminacion::Liquidacion->value => $liquidacion,
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
     * Igual que `leerValor()`, pero para campos numéricos simples (schedule_c,
     * schedule_e, schedule_f) donde no declarar esa forma, o no haber cargado
     * todavía ese campo puntual, es un estado legítimo — 0, no "no disponible"
     * — a diferencia de `ingresos`/`estado_civil`, que sí bloquean el cálculo
     * completo si faltan.
     */
    private function leerNumero(User $cliente, int $taxYear, string $forma, string $campo): float
    {
        return (float) ($this->leerValor($cliente, $taxYear, $forma, $campo) ?? 0);
    }

    /**
     * @return array{disponible: bool, motivo_no_disponible: string}
     */
    private function noDisponible(string $motivo): array
    {
        return ['disponible' => false, 'motivo_no_disponible' => $motivo];
    }
}
