<?php

namespace Database\Seeders;

use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use App\Support\TaxFieldCatalog;
use Illuminate\Database\Seeder;

/**
 * Carga la versión inicial del catálogo editable de campos (sección 2 de la
 * especificación) — a partir de acá el catálogo se administra desde el panel
 * (`/catalogo`), esta es solo la semilla con la que arranca el sistema.
 */
class CatalogoCamposSeeder extends Seeder
{
    /**
     * Año fiscal del baseline que siembra este seeder. Hardcodeado a propósito
     * (no leído de config('tax.current_tax_year')): un seeder reproduce un
     * punto fijo en el tiempo, y acoplarlo al config actual haría que
     * `migrate:fresh --seed` corrido en 2026 —antes de que alguien construya
     * el catálogo real de 2026— sembrara filas 2026 con las definiciones de
     * 2025 mal etiquetadas.
     */
    private const BASELINE_YEAR = 2025;

    public function run(): void
    {
        foreach ($this->transversales() as $campo) {
            $this->crear(CampoCatalogo::TRANSVERSAL, $campo);
        }

        foreach ($this->documentosExtra() as $campo) {
            $this->crear(CampoCatalogo::DOCUMENTOS_EXTRA, $campo);
        }

        foreach ($this->porForma() as $forma => $campos) {
            foreach ($campos as $campo) {
                $this->crear($forma, $campo);
            }
        }

        // TaxFieldCatalog cachea el catálogo con rememberForever(); un reseed
        // manual (migrate:fresh --seed) no pasa por CatalogoController, que es
        // el único lugar que hoy invalida esa caché. Sin esto, un entorno con
        // CACHE_STORE persistente (database, redis) se queda sirviendo el
        // catálogo de ANTES del reseed, indefinidamente.
        TaxFieldCatalog::invalidate();
    }

    /**
     * @param  array<string, mixed>  $campo
     */
    private function crear(string $forma, array $campo): void
    {
        CampoCatalogo::query()->firstOrCreate(
            ['forma' => $forma, 'clave' => $campo['campo'], 'tax_year' => self::BASELINE_YEAR],
            [
                'tipo_campo' => $campo['tipo'],
                'tipo_dato' => $campo['tipo_dato'] ?? null,
                'formatos_aceptados' => $campo['formatos_aceptados'] ?? null,
                'subcampos' => $campo['subcampos'] ?? null,
                'obligatorio' => $campo['obligatorio'] ?? true,
                'sensible' => $campo['sensible'] ?? false,
                'unico_por_cliente' => $campo['unico_por_cliente'] ?? false,
            ],
        );
    }

    /**
     * Identidad del cliente y los pocos documentos núcleo que se le piden a
     * todo el mundo — el resto de documentos opcionales vive en
     * `documentosExtra()`, bajo CampoCatalogo::DOCUMENTOS_EXTRA.
     *
     * @return array<int, array<string, mixed>>
     */
    private function transversales(): array
    {
        return [
            $this->campo('identificacion_ssn_itin', FieldKind::Dato, tipoDato: FieldDataType::String, sensible: true, unicoPorCliente: true),
            $this->campo('info_conyuge', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['nombre_completo', 'fecha_nacimiento', 'ssn'], sensible: true, unicoPorCliente: true),
            $this->campo('info_dependientes', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject, subcampos: [
                'nombre_completo', 'fecha_nacimiento', 'ssn',
                'relacion', 'meses_en_hogar', 'estudiante_tiempo_completo', 'discapacitado',
                'provee_mas_50_soporte_propio', 'ingreso_bruto_anual', 'custodia_compartida_sin_conflicto',
            ], sensible: true, unicoPorCliente: true),
            $this->campo('w2', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], unicoPorCliente: true),
            $this->campo('form_1099_nec', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], unicoPorCliente: true),
            $this->campo('form_1095_a', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            // Hechos crudos (no la conclusión) para que el motor de reglas calcule
            // el filing status — ver App\Services\Reglas\FilingStatusCalculator.
            $this->campo('estado_civil', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: [
                'casado_al_31_dic', 'convivio_conyuge_ultimos_6_meses', 'costeo_mas_mitad_hogar',
                'existe_persona_calificable', 'conyuge_fallecio_en_anio', 'anio_fallecimiento_conyuge',
            ], unicoPorCliente: true),
        ];
    }

    /**
     * Documentos opcionales que, igual que `transversales()`, se piden
     * siempre sin importar qué forma(s) tenga el cliente — se agrupan aparte
     * (CampoCatalogo::DOCUMENTOS_EXTRA) porque no son el núcleo de identidad
     * del cliente, sino documentos que puede enviar además.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentosExtra(): array
    {
        return [
            // Documentos de inversión/retiro/vivienda — no todo cliente los
            // recibe (depende de si tuvo intereses, dividendos, distribuciones
            // de retiro, desempleo/reembolso estatal, o hipoteca/préstamo
            // estudiantil ese año), por eso obligatorio: false. Ver
            // RelacionesDocumentoCampoSeeder para a qué campo de qué forma
            // alimenta cada uno (matriz GTS 1040 2025).
            $this->campo('form_1099_int', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_div', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_r', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_g', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1098', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1098_e', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            // Fase 6 (auditoría completa de la matriz GTS 1040 2025, más allá de
            // la primera pasada): documentos que la matriz identifica pero que
            // todavía no existían en el catálogo. Ver RelacionesDocumentoCampoSeeder
            // para las relaciones ciertas; SSA-1099 y 1099-B/DA y 1098-T
            // tienen `revela` — el resto (1099-MISC, 1099-K, 1099-S, K-1 personal,
            // 1099-SA) la matriz misma los marca como "fact-dependent"/"no
            // automáticamente gravable en su totalidad", así que solo se
            // recolectan como documento, sin relación automática (ver
            // GROUNDING ESTRICTO del prompt: nunca inventar una relación ambigua).
            $this->campo('ssa_1099', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_b', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1098_t', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_misc', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_k', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_s', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            // K-1 que el cliente recibe como socio/beneficiario de una entidad
            // AJENA (no la suya) — distinto de `datos_k1` dentro de form_1065/
            // form_1120_s, que es el K-1 que la ENTIDAD del cliente emite a sus
            // propios socios.
            $this->campo('k1_recibido', FieldKind::Documento, formatos: ['pdf'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_w2g', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_c', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_sa', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_5498_sa', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('declaracion_anio_anterior', FieldKind::Documento, formatos: ['pdf'], obligatorio: false, unicoPorCliente: true),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function porForma(): array
    {
        return [
            TaxForm::Form1040->value => [
                // Desglosado (no un Number suelto): sin esto no se puede calcular
                // AGI — ver App\Services\Reglas\AgiCalculator.
                // 'seguridad_social' (Fase 6) es un subcampo NUEVO agregado a un
                // campo ya existente en producción — CatalogoCamposSeeder usa
                // firstOrCreate por (forma, clave, tax_year), así que esto solo
                // toma efecto en instalaciones frescas; la fila ya sembrada en
                // producción se actualiza aparte con una migración de datos
                // (ver 2026_08_11_090000_fase6_documentos_faltantes_matriz_gts.php).
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: [
                    'salarios', 'intereses_dividendos', 'ganancias_capital',
                    'ingresos_jubilacion', 'otros_ingresos', 'ajustes_ingreso', 'seguridad_social',
                ]),
                $this->campo('deducciones', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'jpeg']),
                $this->campo('impuestos_retenidos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('info_bancaria', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['banco', 'tipo_cuenta', 'numero_cuenta', 'routing_number'], sensible: true),
                // Alimenta el Child and Dependent Care Credit (Form 2441) — ver
                // App\Services\Reglas\CreditEligibilityCalculator. No todos los
                // clientes tienen gastos de cuidado, por eso obligatorio: false.
                $this->campo('gastos_cuidado_dependientes', FieldKind::Mixto, tipoDato: FieldDataType::Object, formatos: ['pdf', 'jpg', 'jpeg'], subcampos: [
                    'proveedor_nombre', 'proveedor_ssn_ein', 'monto_anual', 'dependiente_relacionado',
                ], obligatorio: false, sensible: true),
                // Fase 6 — campos nuevos identificados en la auditoría completa
                // de la matriz GTS 1040 2025 (1098-T, 1095-A, impuesto extranjero
                // de 1099-DIV casilla 7, y las deducciones nuevas de 2025).
                $this->campo('gastos_educacion', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'jpeg'], obligatorio: false),
                $this->campo('marketplace_seguro', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: [
                    'premium_mensual', 'slcsp', 'aptc_recibido',
                ], obligatorio: false),
                $this->campo('impuesto_extranjero_pagado', FieldKind::Dato, tipoDato: FieldDataType::Number, obligatorio: false),
                // Hechos crudos para Schedule 1-A (línea 13b) — no es la
                // conclusión de cuánto deducir, solo lo que el motor de reglas
                // necesita para calcularlo más adelante.
                $this->campo('beneficios_2025', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: [
                    'propinas_reportadas', 'horas_extra_pagadas', 'interes_prestamo_auto', 'es_adulto_mayor',
                ], obligatorio: false),
            ],
            TaxForm::ScheduleC->value => [
                $this->campo('estados_bancarios', FieldKind::Documento, formatos: ['pdf', 'xlsx', 'csv']),
                $this->campo('ingresos_negocio', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos_deducibles_negocio', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'jpeg', 'csv']),
                $this->campo('millaje', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('costo_ventas', FieldKind::Dato, tipoDato: FieldDataType::Number),
            ],
            TaxForm::ScheduleE->value => [
                $this->campo('estados_bancarios', FieldKind::Documento, formatos: ['pdf', 'xlsx', 'csv']),
                $this->campo('ingresos_renta', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos_propiedad', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'jpeg']),
                $this->campo('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('intereses_hipotecarios', FieldKind::Documento, formatos: ['pdf']),
                $this->campo('impuestos_propiedad', FieldKind::Documento, formatos: ['pdf']),
                $this->campo('seguros_propiedad', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form1065->value => [
                $this->campo('estados_bancarios', FieldKind::Documento, formatos: ['pdf', 'xlsx', 'csv']),
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('pasivos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('aportes_socios', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('porcentajes_participacion', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('datos_k1', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form1120->value => [
                $this->campo('estados_bancarios', FieldKind::Documento, formatos: ['pdf', 'xlsx', 'csv']),
                $this->campo('estados_financieros', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('impuestos_pagados', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('pasivos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('balance_general', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
            ],
            TaxForm::Form1120S->value => [
                $this->campo('estados_bancarios', FieldKind::Documento, formatos: ['pdf', 'xlsx', 'csv']),
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('estados_financieros', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
                $this->campo('nomina_compensacion_accionistas', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf']),
                $this->campo('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('datos_k1', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::ScheduleF->value => [
                $this->campo('estados_bancarios', FieldKind::Documento, formatos: ['pdf', 'xlsx', 'csv']),
                $this->campo('ventas_agricolas', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('subsidios', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos_operacion', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'jpeg']),
                $this->campo('maquinaria', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('animales', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('inventario', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
            ],
            TaxForm::Form1041->value => [
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('info_beneficiarios', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject, sensible: true),
                $this->campo('distribuciones', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('documentos_fideicomiso', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form990->value => [
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('donaciones', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('actividades_programas', FieldKind::Dato, tipoDato: FieldDataType::String),
                $this->campo('compensacion_directivos', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('gobierno_corporativo', FieldKind::Dato, tipoDato: FieldDataType::String),
            ],
            TaxForm::Form1040Nr->value => [
                $this->campo('ingresos_fuente_usa', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('formularios_retencion', FieldKind::Documento, formatos: ['pdf']),
                $this->campo('info_migratoria', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['tipo_visa', 'fecha_entrada_usa', 'estatus_migratorio']),
                $this->campo('tratados_tributarios', FieldKind::Dato, tipoDato: FieldDataType::String),
                $this->campo('deducciones_permitidas', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'jpeg']),
            ],
        ];
    }

    /**
     * @param  array<int, string>|null  $formatos
     * @param  array<int, string>|null  $subcampos
     * @return array<string, mixed>
     */
    private function campo(
        string $campo,
        FieldKind $tipo,
        ?FieldDataType $tipoDato = null,
        ?array $formatos = null,
        ?array $subcampos = null,
        bool $obligatorio = true,
        bool $sensible = false,
        bool $unicoPorCliente = false,
    ): array {
        return [
            'campo' => $campo,
            'tipo' => $tipo,
            'tipo_dato' => $tipoDato,
            'formatos_aceptados' => $formatos,
            'subcampos' => $subcampos,
            'obligatorio' => $obligatorio,
            'sensible' => $sensible,
            'unico_por_cliente' => $unicoPorCliente,
        ];
    }
}
