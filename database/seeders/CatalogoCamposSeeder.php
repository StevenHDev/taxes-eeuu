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

        foreach ($this->documentosPromovidosAActivo() as $campo) {
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
            // se_caso_en_anio/se_divorcio_o_separo_en_anio: subcampos agregados
            // en la Fase 3a del plan de cierre de brecha GTS — la matriz
            // distingue "¿se casó, divorció, separó o enviudó durante el año?"
            // de "casado_al_31_dic" (estado A UN MOMENTO puntual, no si CAMBIÓ
            // durante el año). conyuge_fallecio_en_anio/anio_fallecimiento_conyuge
            // ya cubrían el caso de viudez. En instalaciones ya provisionadas
            // (producción), esta fila ya existe — ver migración
            // 2026_09_18_200000_fase3a_agrega_cambio_estado_civil_en_anio.php.
            $this->campo('estado_civil', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: [
                'casado_al_31_dic', 'convivio_conyuge_ultimos_6_meses', 'costeo_mas_mitad_hogar',
                'existe_persona_calificable', 'conyuge_fallecio_en_anio', 'anio_fallecimiento_conyuge',
                'se_caso_en_anio', 'se_divorcio_o_separo_en_anio',
            ], unicoPorCliente: true),
            // Fase 1 del plan de cierre de brecha GTS (compliance crítico P1,
            // ver el artifact "Matriz GTS 1040"): la pregunta de activos
            // digitales del propio Form 1040 — se responde "si"/"no" siempre,
            // nunca modo="no_aplica" (ver EventoRecoleccionService::validarString
            // y ActivosResolver, que dependen de ese literal exacto).
            $this->campo('activos_digitales', FieldKind::Dato, tipoDato: FieldDataType::String, unicoPorCliente: true),
            // FBAR/FATCA: compuerta sí/no obligatoria + detalle condicional
            // (solo si respondió "si") — ver PromptActivoStepsSeeder, paso
            // Condicional de cuentas_extranjero_detalle.
            $this->campo('cuentas_extranjero', FieldKind::Dato, tipoDato: FieldDataType::String, unicoPorCliente: true),
            $this->campo('cuentas_extranjero_detalle', FieldKind::Mixto, tipoDato: FieldDataType::Object, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], subcampos: [
                'pais', 'institucion', 'valor_maximo_anual',
            ], obligatorio: false, unicoPorCliente: true),
            // Distinto de form_1099_s genérico: acá aplica la exclusión de
            // $250k/$500k (ver nota del paso en PromptActivoStepsSeeder).
            $this->campo('venta_residencia_principal', FieldKind::Mixto, tipoDato: FieldDataType::Object, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], subcampos: [
                'fecha_venta', 'precio_venta', 'base_costo',
            ], obligatorio: false, unicoPorCliente: true),
            // Fase 3a del plan de cierre de brecha GTS: identidad y eventos
            // del propio contribuyente que faltaban en el catálogo (sección 1
            // y 2 del artifact "Matriz GTS 1040") — hechos que todo cliente
            // tiene una respuesta real para dar, sin concepto de "no aplica".
            $this->campo('fecha_nacimiento_contribuyente', FieldKind::Dato, tipoDato: FieldDataType::String, unicoPorCliente: true),
            $this->campo('direccion_contribuyente', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: [
                'calle', 'ciudad', 'estado', 'codigo_postal',
            ], unicoPorCliente: true),
            $this->campo('ocupacion', FieldKind::Dato, tipoDato: FieldDataType::String, unicoPorCliente: true),
            // Compuertas sí/no de compliance — mismo tratamiento que
            // activos_digitales/cuentas_extranjero (Fase 1): siempre se
            // responden "si"/"no" exactamente, nunca modo="no_aplica" (ver
            // EventoRecoleccionService::validarString).
            $this->campo('puede_ser_reclamado_como_dependiente', FieldKind::Dato, tipoDato: FieldDataType::String, unicoPorCliente: true),
            $this->campo('vivio_trabajo_fuera_eeuu', FieldKind::Dato, tipoDato: FieldDataType::String, unicoPorCliente: true),
            // No todo cliente tiene un IP PIN — obligatorio: false.
            $this->campo('ip_pin', FieldKind::Dato, tipoDato: FieldDataType::String, obligatorio: false, unicoPorCliente: true),
            // Solo aplica si hay un acuerdo de custodia compartida (ver
            // info_dependientes.custodia_compartida_sin_conflicto) — no todo
            // cliente con dependientes lo tiene, por eso obligatorio: false.
            $this->campo('form_8332', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
        ];
    }

    /**
     * Documentos opcionales que, igual que `transversales()`, se piden
     * siempre sin importar qué forma(s) tenga el cliente — se agrupan aparte
     * (CampoCatalogo::DOCUMENTOS_EXTRA) porque no son el núcleo de identidad
     * del cliente, sino documentos que puede enviar además.
     *
     * Vacío desde la Fase 2 del plan de cierre de brecha GTS (ver el artifact
     * "Matriz GTS 1040"): los 17 documentos que antes vivían acá se
     * promovieron a ACTIVO (ver documentosPromovidosAActivo()) — el cliente
     * ahora se pregunta directo por cada uno, en vez de esperar a que los
     * suba espontáneamente. form_1098_t es la única excepción deliberada:
     * se queda pasivo porque `gastos_educacion` (form_1040, Mixto) ya
     * pregunta lo mismo y acepta ese mismo documento como respuesta —
     * promoverlo aparte sería preguntar dos veces por lo mismo. El mecanismo
     * de `documentos_extra` se mantiene disponible para que un admin agregue
     * desde el panel un documento genuinamente pasivo en el futuro.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentosExtra(): array
    {
        return [
            $this->campo('form_1098_t', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
        ];
    }

    /**
     * Fase 2 del plan de cierre de brecha GTS: documentos que antes vivían en
     * documentosExtra() (forma documentos_extra, pasivos — nunca se
     * preguntaban) y ahora se preguntan directo (forma transversal, ver
     * decisión de diseño del dueño del producto en el plan). Los mismos
     * campo() de antes, sin cambios en tipo/formatos/sensibilidad — solo
     * cambia la pseudo-forma bajo la que se siembran (ver run()) y que ahora
     * tienen un PromptActivoStep (ver PromptActivoStepsSeeder). Publicar esto
     * en producción requiere además migrar las filas ya guardadas en
     * catalogo_campos/campos_cliente de 'documentos_extra' a 'transversal'
     * para estas mismas claves (ver migración
     * 2026_09_18_190000_promueve_documentos_extra_a_activo.php) — de lo
     * contrario un cliente que ya subió alguno de estos espontáneamente
     * volvería a que se lo pidan.
     *
     * form_1099_r y ssa_1099 se preguntan juntos como un solo paso `Grupo`
     * ("Retiro y jubilación", ver PromptActivoStepsSeeder) — el resto,
     * individualmente: ninguno de los otros grupos propuestos en el artifact
     * tiene hoy más de un campo real en el catálogo (sus demás miembros
     * siguen "sin modelar", trabajo de la Fase 3).
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentosPromovidosAActivo(): array
    {
        return [
            $this->campo('form_1099_int', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_div', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_r', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_g', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1098', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1098_e', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('ssa_1099', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
            $this->campo('form_1099_b', FieldKind::Documento, formatos: ['pdf', 'jpg', 'jpeg', 'png', 'heic'], obligatorio: false, unicoPorCliente: true),
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
                // Box 5 del W-2 (Medicare wages) — necesario para
                // AdditionalMedicareTaxCalculator (Form 8959). Antes de este
                // campo, DeterminacionFiscalService sustituía este valor por
                // ingresos.salarios (Box 1), que subestima el impuesto para
                // cualquier cliente con descuentos pre-tax de nómina (401k,
                // HSA, sección 125): Box 5 siempre es >= Box 1 en esos casos.
                // Fila nueva vía CatalogoCamposSeeder — instalaciones ya
                // provisionadas la reciben aparte (ver
                // 2026_09_17_130000_agrega_salarios_medicare_a_catalogo.php).
                $this->campo('salarios_medicare', FieldKind::Dato, tipoDato: FieldDataType::Number),
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
                // Fase 1 del plan de cierre de brecha GTS: línea 26 del Form
                // 1040 (pagos estimados + pago con extensión + reembolso de
                // año anterior aplicado) — antes de esto, SettlementCalculator
                // solo recibía impuestos_retenidos, subestimando total_pagos
                // para cualquier cliente que hizo alguno de estos tres pagos.
                // Ver DeterminacionFiscalService::calcularPara(). Campos de
                // forma real (no transversales): `siguiente` los pregunta
                // igual que cualquier otro campo de form_1040, sin necesidad
                // de un paso en PromptActivoStepsSeeder.
                $this->campo('pagos_estimados', FieldKind::Dato, tipoDato: FieldDataType::Number, obligatorio: false),
                $this->campo('pago_con_extension', FieldKind::Dato, tipoDato: FieldDataType::Number, obligatorio: false),
                $this->campo('reembolso_anio_anterior_aplicado', FieldKind::Dato, tipoDato: FieldDataType::Number, obligatorio: false),
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
