<?php

namespace Database\Seeders;

use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use App\Models\RelacionDocumentoCampo;
use App\Support\TaxFieldCatalog;
use Illuminate\Database\Seeder;

/**
 * Relaciones confirmadas "documento fuente → campo que ya resuelve", derivadas
 * de la matriz de trazabilidad GTS Form 1040 2025 (pestañas W2_Trace y
 * Source_Forms). Sustituye la sección estática "RELACIONES CONOCIDAS ENTRE
 * DOCUMENTOS Y CAMPOS" que antes vivía como texto fijo en docs/prompt.md —
 * el agente externo ahora la lee vía la clave `revela` de
 * TaxFieldCatalog::pendientesPara(), nunca la memoriza.
 *
 * Cada entrada requiere que tanto el campo-documento como el campo-destino ya
 * existan en `catalogo_campos` para el mismo año (ver CatalogoCamposSeeder) —
 * de lo contrario se omite silenciosamente en vez de romper el seed completo,
 * porque un año fiscal futuro puede reordenar o renombrar campos antes de que
 * alguien actualice esta lista.
 */
class RelacionesDocumentoCampoSeeder extends Seeder
{
    private const BASELINE_YEAR = 2025;

    public function run(): void
    {
        foreach ($this->relaciones() as $r) {
            if (! $this->campoExiste($r['documento_forma'], $r['documento_campo'])) {
                continue;
            }

            if (! $this->campoExiste($r['campo_destino_forma'], $r['campo_destino'])) {
                continue;
            }

            RelacionDocumentoCampo::query()->firstOrCreate(
                [
                    'documento_campo' => $r['documento_campo'],
                    'campo_destino_forma' => $r['campo_destino_forma'],
                    'campo_destino' => $r['campo_destino'],
                    'subcampo_destino' => $r['subcampo_destino'] ?? null,
                    'tax_year' => self::BASELINE_YEAR,
                ],
                [
                    'documento_forma' => $r['documento_forma'],
                    'descripcion' => $r['descripcion'],
                    'acumulable' => $r['acumulable'] ?? false,
                ],
            );
        }

        TaxFieldCatalog::invalidate();
    }

    private function campoExiste(string $forma, string $campo): bool
    {
        return TaxFieldCatalog::find(self::BASELINE_YEAR, $forma, $campo) !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relaciones(): array
    {
        $transversal = CampoCatalogo::TRANSVERSAL;
        $f1040 = TaxForm::Form1040->value;
        $schedC = TaxForm::ScheduleC->value;

        return [
            // W-2 — ver matriz pestaña W2_Trace
            [
                'documento_forma' => $transversal, 'documento_campo' => 'w2',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'salarios',
                'descripcion' => 'Box 1 (Wages, tips, other compensation) del W-2 es el salario total del cliente.',
            ],
            [
                'documento_forma' => $transversal, 'documento_campo' => 'w2',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'impuestos_retenidos', 'subcampo_destino' => null,
                'descripcion' => 'Box 2 (Federal income tax withheld) del W-2 suma directo a la retención federal total.',
                'acumulable' => true,
            ],
            [
                'documento_forma' => $transversal, 'documento_campo' => 'w2',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'gastos_cuidado_dependientes', 'subcampo_destino' => 'monto_anual',
                'descripcion' => 'Box 10 (Dependent care benefits) del W-2 es el monto anual de beneficios de cuidado de dependientes recibido vía el empleador.',
            ],

            // 1099-NEC — ver matriz pestaña Source_Forms + relación ya confirmada
            // en docs/prompt.md antes de esta migración.
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_nec',
                'campo_destino_forma' => $schedC, 'campo_destino' => 'ingresos_negocio', 'subcampo_destino' => null,
                'descripcion' => 'Casilla 1 (Nonemployee compensation) del 1099-NEC es el ingreso bruto del negocio como independiente.',
            ],
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_nec',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'impuestos_retenidos', 'subcampo_destino' => null,
                'descripcion' => 'Casilla 4 (Federal income tax withheld) del 1099-NEC suma a la retención federal total.',
                'acumulable' => true,
            ],

            // 1099-INT
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_int',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'intereses_dividendos',
                'descripcion' => 'Casilla 1 (Interest income) del 1099-INT es interés gravable a incluir en ingresos.',
                'acumulable' => true,
            ],
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_int',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'impuestos_retenidos', 'subcampo_destino' => null,
                'descripcion' => 'Casilla 4 (Federal income tax withheld) del 1099-INT suma a la retención federal total.',
                'acumulable' => true,
            ],

            // 1099-DIV
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_div',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'intereses_dividendos',
                'descripcion' => 'Casilla 1a (Total ordinary dividends) del 1099-DIV es dividendo gravable a incluir en ingresos.',
                'acumulable' => true,
            ],
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_div',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'impuestos_retenidos', 'subcampo_destino' => null,
                'descripcion' => 'Casilla 4 (Federal income tax withheld) del 1099-DIV suma a la retención federal total.',
                'acumulable' => true,
            ],

            // 1099-R
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_r',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'ingresos_jubilacion',
                'descripcion' => 'Casilla 2a (Taxable amount) del 1099-R es la porción gravable de la distribución de retiro/pensión.',
            ],
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_r',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'impuestos_retenidos', 'subcampo_destino' => null,
                'descripcion' => 'Casilla 4 (Federal income tax withheld) del 1099-R suma a la retención federal total.',
                'acumulable' => true,
            ],

            // 1099-G
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_g',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'otros_ingresos',
                'descripcion' => 'Casilla 1 (Unemployment compensation) o casilla 2 (state/local refund, si gravable) del 1099-G es otro ingreso a reportar.',
                'acumulable' => true,
            ],

            // 1098 (hipoteca)
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1098',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'deducciones', 'subcampo_destino' => null,
                'descripcion' => 'Casilla 1 (Mortgage interest received) del 1098 es interés hipotecario deducible si el cliente itemiza (Schedule A).',
            ],

            // 1098-E (interés préstamo estudiantil)
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1098_e',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'ajustes_ingreso',
                'descripcion' => 'Casilla 1 (Student loan interest received) del 1098-E es un ajuste al ingreso (above-the-line), sujeto a límites de MAGI.',
                'acumulable' => true,
            ],

            // Fase 6 — relaciones ciertas identificadas en la auditoría completa
            // de la matriz (pestañas Source_Forms/Schedule_Flows/1040_Trace).
            // form_1099_misc, form_1099_k, form_1099_s, k1_recibido y
            // form_1099_sa NO tienen relación acá a propósito: la matriz misma
            // los marca como "fact-dependent"/"no automáticamente gravable en su
            // totalidad" — no hay una casilla única que determine con certeza el
            // campo destino, así que solo se recolectan como documento (ver
            // GROUNDING ESTRICTO del prompt del agente).

            // SSA-1099 / RRB-1099 (Seguro Social)
            [
                'documento_forma' => $transversal, 'documento_campo' => 'ssa_1099',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'seguridad_social',
                'descripcion' => 'Casilla 5 (Net benefits) del SSA-1099 es el beneficio bruto de Seguro Social; la porción gravable la calcula el motor de reglas según el ingreso combinado (Social Security Benefits Worksheet).',
            ],

            // 1099-B / 1099-DA (ventas de inversiones/cripto)
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_b',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'ganancias_capital',
                'descripcion' => 'Ganancia/pérdida neta reportada (proceeds menos basis) del 1099-B/1099-DA es la ganancia de capital a incluir en ingresos — aproximación: no distingue corto/largo plazo ni lote por lote.',
            ],

            // 1098-T (educación)
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1098_t',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'gastos_educacion', 'subcampo_destino' => null,
                'descripcion' => 'Casilla 1 (Payments received for qualified tuition and related expenses) del 1098-T son los gastos calificados de educación para el crédito educativo (Form 8863).',
            ],

            // 1095-A (Marketplace / Affordable Care Act)
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1095_a',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'marketplace_seguro', 'subcampo_destino' => 'premium_mensual',
                'descripcion' => 'Columna A (Monthly premium amount) del 1095-A, parte III, es la prima mensual pagada por el seguro del Marketplace.',
            ],
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1095_a',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'marketplace_seguro', 'subcampo_destino' => 'slcsp',
                'descripcion' => 'Columna B (Monthly second lowest cost silver plan) del 1095-A, parte III, es el SLCSP mensual, necesario para reconciliar el Premium Tax Credit (Form 8962).',
            ],
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1095_a',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'marketplace_seguro', 'subcampo_destino' => 'aptc_recibido',
                'descripcion' => 'Columna C (Monthly advance payment of premium tax credit) del 1095-A, parte III, es el APTC recibido por adelantado a reconciliar en el Form 8962.',
            ],

            // W-2G (ganancias de juego)
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_w2g',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'otros_ingresos',
                'descripcion' => 'Casilla 1 (Reportable winnings) del W-2G es ingreso de juego a reportar como otro ingreso.',
                'acumulable' => true,
            ],

            // 1099-C (cancelación de deuda)
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_c',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'otros_ingresos',
                'descripcion' => 'Casilla 2 (Amount of debt discharged) del 1099-C es ingreso por cancelación de deuda a incluir en otros ingresos, sujeto a exclusiones (insolvencia, quiebra) que el motor de reglas no evalúa — verificar con el cliente antes de usarlo tal cual.',
                'acumulable' => true,
            ],

            // 5498-SA (aportes a HSA — deducible; distinto de 1099-SA, que son
            // distribuciones y no tiene relación cierta por depender de si
            // fueron gastos médicos calificados).
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_5498_sa',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'ingresos', 'subcampo_destino' => 'ajustes_ingreso',
                'descripcion' => 'Casilla 2 (Total contributions made) del 5498-SA es el aporte a HSA deducible como ajuste al ingreso (Form 8889).',
            ],

            // Impuesto extranjero pagado — casilla adicional del 1099-DIV que ya
            // es transversal en el catálogo (no requiere un documento nuevo).
            [
                'documento_forma' => $transversal, 'documento_campo' => 'form_1099_div',
                'campo_destino_forma' => $f1040, 'campo_destino' => 'impuesto_extranjero_pagado', 'subcampo_destino' => null,
                'descripcion' => 'Casilla 7 (Foreign tax paid) del 1099-DIV es impuesto extranjero pagado, elegible para el crédito del Form 1116.',
            ],
        ];
    }
}
