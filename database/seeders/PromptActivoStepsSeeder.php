<?php

namespace Database\Seeders;

use App\Enums\TipoPromptActivoStep;
use App\Models\PromptActivoStep;
use Illuminate\Database\Seeder;

/**
 * Siembra la lista ACTIVOS vigente hoy en prompt_actuales/fases/recoleccion.md
 * (sección "CAMPOS TRANSVERSALES: ACTIVOS VS. PASIVOS") — reproduce el mismo
 * orden y las mismas condiciones/notas, para que compilarla con
 * ActivosPromptComposer no cambie el comportamiento del agente al publicarla.
 * Idempotente (trunca y re-inserta): el orden es la fuente de verdad, no hay
 * forma estable de hacer upsert por clave natural cuando `campo` es null
 * (bifurcacion).
 */
class PromptActivoStepsSeeder extends Seeder
{
    public function run(): void
    {
        PromptActivoStep::query()->delete();

        $pasos = [
            ['orden' => 1, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'identificacion_ssn_itin'],
            [
                'orden' => 2, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'estado_civil',
                'nota' => 'incluye, además del estado civil al 31 de diciembre, si se casó, se divorció o se '
                    .'separó durante el año (subcampos se_caso_en_anio/se_divorcio_o_separo_en_anio — Fase 3a) '
                    .'— distinto de si enviudó, que ya cubre conyuge_fallecio_en_anio.',
            ],
            [
                'orden' => 3, 'tipo' => TipoPromptActivoStep::Condicional, 'campo' => 'info_conyuge',
                'condicion' => 'estado_civil indica que el cliente es casado',
                'nota_si_no_aplica' => 'Si es soltero, no se pregunta.',
            ],
            [
                'orden' => 4, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'info_dependientes',
                'nota' => 'pregunta primero si tiene dependientes; si dice que sí, recolecta el dato completo, '
                    .'incluyendo los 10 subcampos (nombre_completo, ssn, fecha_nacimiento, relacion, meses_en_hogar, '
                    .'estudiante_tiempo_completo, discapacitado, provee_mas_50_soporte_propio, ingreso_bruto_anual, '
                    .'custodia_compartida_sin_conflicto) — sin excepción de ninguno de ellos.',
            ],
            [
                'orden' => 5, 'tipo' => TipoPromptActivoStep::Bifurcacion, 'etiqueta' => 'Empleo',
                'pregunta' => '¿Eres empleado?', 'campo_si' => 'w2', 'campo_no' => 'form_1099_nec',
            ],
            [
                'orden' => 6, 'tipo' => TipoPromptActivoStep::DocumentoConNota, 'campo' => 'form_1095_a',
                'nota' => 'se mantiene la lógica ya definida arriba (preguntar en lenguaje simple sobre seguro '
                    .'del Marketplace antes de nombrar el formulario).',
            ],
            // Fase 1 del plan de cierre de brecha GTS (compliance crítico
            // P1) — se agregan al final de la lista existente, sin
            // reordenar los pasos 1-6 ya publicados.
            [
                'orden' => 7, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'activos_digitales',
                'nota' => 'pregunta textual del IRS, obligatoria siempre (nunca modo="no_aplica"): "¿En algún '
                    .'momento del año recibiste, vendiste, intercambiaste o de otra forma dispusiste de un '
                    .'activo digital (criptomonedas, NFTs u otro activo digital)?". Guarda la respuesta como '
                    .'"si" o "no" exactamente (tipo_dato string) — nunca otra palabra ni variante.',
            ],
            [
                'orden' => 8, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'cuentas_extranjero',
                'nota' => 'compuerta obligatoria (nunca modo="no_aplica"), en lenguaje simple: "¿Tuviste en '
                    .'algún momento del año cuentas bancarias, de inversión, u otros activos financieros fuera '
                    .'de Estados Unidos?". Guarda la respuesta como "si" o "no" exactamente (tipo_dato string) '
                    .'— nunca otra palabra ni variante. Si responde "si", a continuación se pide el detalle '
                    .'(país, institución, valor máximo del año) — no lo pidas en este mismo turno.',
            ],
            [
                'orden' => 9, 'tipo' => TipoPromptActivoStep::Condicional, 'campo' => 'cuentas_extranjero_detalle',
                'condicion' => 'cuentas_extranjero fue respondido como "si"',
                'nota_si_no_aplica' => 'Si respondió "no", no se pregunta.',
            ],
            [
                'orden' => 10, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'venta_residencia_principal',
                'nota' => 'pregunta en lenguaje simple si vendió su residencia principal durante el año '
                    .'(distinto de cualquier otra propiedad de alquiler/inversión, que se cubre en Schedule E); '
                    .'si confirma que sí, pide fecha de venta, precio de venta y costo base original (para '
                    .'evaluar la exclusión de $250,000/$500,000, que solo aplica a la residencia principal) — '
                    .'nunca calcules tú la exclusión, solo recolecta los datos.',
            ],
            // Fase 2 del plan de cierre de brecha GTS: documentos que antes
            // vivían pasivos en documentos_extra (ver CatalogoCamposSeeder
            // ::documentosPromovidosAActivo()) y ahora se preguntan directo.
            // form_1099_r + ssa_1099 van juntos como Grupo ("Retiro y
            // jubilación", el único de los 8 grupos propuestos en el artifact
            // con más de un campo real en el catálogo hoy); el resto,
            // individualmente — sus demás compañeros de grupo en el artifact
            // todavía no existen como campo (Fase 3).
            [
                'orden' => 11, 'tipo' => TipoPromptActivoStep::Grupo, 'etiqueta' => 'Retiro y jubilación',
                'pregunta' => '¿Recibiste dinero de tu retiro, pensión, o Seguro Social (Social Security) '
                    .'este año?',
                'miembros' => ['form_1099_r', 'ssa_1099'],
            ],
            [
                'orden' => 12, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_int',
                'nota' => 'pregunta simple si tuvo intereses bancarios o de cuentas de inversión durante el año.',
            ],
            [
                'orden' => 13, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_div',
                'nota' => 'pregunta simple si recibió dividendos de inversiones durante el año.',
            ],
            [
                'orden' => 14, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_b',
                'nota' => 'pregunta simple si vendió acciones, ETFs, fondos, bonos u otras inversiones '
                    .'durante el año.',
            ],
            [
                'orden' => 15, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_g',
                'nota' => 'pregunta simple si recibió desempleo o un reembolso de impuestos estatales/locales '
                    .'durante el año.',
            ],
            [
                'orden' => 16, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1098',
                'nota' => 'pregunta simple si pagó intereses hipotecarios sobre su residencia durante el año.',
            ],
            [
                'orden' => 17, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1098_e',
                'nota' => 'pregunta simple si pagó intereses de préstamos estudiantiles durante el año.',
            ],
            [
                'orden' => 18, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_misc',
                'nota' => 'pregunta simple si recibió regalías (royalties) u otros ingresos varios reportados '
                    .'en un 1099-MISC durante el año.',
            ],
            [
                'orden' => 19, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_k',
                'nota' => 'pregunta simple si recibió ingresos por alquiler de corto plazo (Airbnb, VRBO) o '
                    .'pagos por plataformas de pago (Zelle, Venmo, PayPal) reportados en un 1099-K durante el año.',
            ],
            [
                'orden' => 20, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_s',
                'nota' => 'pregunta simple si vendió una propiedad distinta de su residencia principal '
                    .'(terreno, segunda vivienda, propiedad de alquiler) durante el año — distinto de '
                    .'venta_residencia_principal, que ya tiene su propia pregunta.',
            ],
            [
                'orden' => 21, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'k1_recibido',
                'nota' => 'pregunta simple si recibió un Schedule K-1 durante el año, de una sociedad '
                    .'(partnership), una S corporation, o un fideicomiso/sucesión — un único documento cubre '
                    .'los tres casos, no hace falta distinguir cuál.',
            ],
            [
                'orden' => 22, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_w2g',
                'nota' => 'pregunta simple si tuvo ganancias reportables de juego (casino, lotería) durante '
                    .'el año.',
            ],
            [
                'orden' => 23, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_c',
                'nota' => 'pregunta simple si tuvo una cancelación o condonación de deuda durante el año.',
            ],
            [
                'orden' => 24, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_1099_sa',
                'nota' => 'pregunta simple si recibió una distribución de su HSA durante el año.',
            ],
            [
                'orden' => 25, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_5498_sa',
                'nota' => 'pregunta simple si hizo aportes a su HSA durante el año.',
            ],
            [
                'orden' => 26, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'declaracion_anio_anterior',
                'nota' => 'pregunta simple si puede compartir su declaración de impuestos del año anterior '
                    .'(útil para pérdidas de capital o créditos arrastrados).',
            ],
            // Fase 3a del plan de cierre de brecha GTS: identidad y eventos
            // del propio contribuyente (secciones 1 y 2 del artifact), sin
            // dependencias de las fases anteriores — se agregan al final.
            [
                'orden' => 27, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'fecha_nacimiento_contribuyente',
                'nota' => 'pregunta simple la fecha de nacimiento del propio contribuyente — distinto de '
                    .'info_conyuge.fecha_nacimiento (que nunca se pregunta, ver más abajo) e '
                    .'info_dependientes.fecha_nacimiento (que sí se pregunta como parte de ese campo).',
            ],
            [
                'orden' => 28, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'direccion_contribuyente',
                'nota' => 'pregunta la dirección actual del cliente (calle, ciudad, estado, código postal), '
                    .'en un único mensaje, no subcampo por subcampo.',
            ],
            [
                'orden' => 29, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'ocupacion',
                'nota' => 'pregunta simple la ocupación u oficio del cliente.',
            ],
            [
                'orden' => 30, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'puede_ser_reclamado_como_dependiente',
                'nota' => 'pregunta obligatoria (nunca modo="no_aplica"), en lenguaje simple: "¿Puede otra '
                    .'persona reclamarte como dependiente en su propia declaración?". Guarda la respuesta como '
                    .'"si" o "no" exactamente (tipo_dato string) — nunca otra palabra ni variante.',
            ],
            [
                'orden' => 31, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'vivio_trabajo_fuera_eeuu',
                'nota' => 'pregunta obligatoria (nunca modo="no_aplica"), en lenguaje simple: "¿Viviste o '
                    .'trabajaste fuera de Estados Unidos en algún momento del año?". Guarda la respuesta como '
                    .'"si" o "no" exactamente (tipo_dato string) — nunca otra palabra ni variante.',
            ],
            [
                'orden' => 32, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'ip_pin',
                'nota' => 'pregunta simple si el IRS le asignó un IP PIN (un código de 6 dígitos, distinto '
                    .'del reembolso) y, si lo tiene, cuál es.',
            ],
            [
                'orden' => 33, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'form_8332',
                'nota' => 'pregunta simple si existe un acuerdo de custodia compartida o un Form 8332 firmado '
                    .'por el otro padre/madre, cediendo el derecho a reclamar a un dependiente.',
            ],
            // Fase 3b del plan de cierre de brecha GTS: soporte de múltiples
            // W-2 (más de un empleador) — ver ActivosResolver::resolverCondicional
            // y ActivosPromptComposer, que le dan a este paso un tratamiento
            // especial (nunca se guarda con valor "si", ver ambas clases).
            [
                'orden' => 34, 'tipo' => TipoPromptActivoStep::Condicional, 'campo' => 'mas_w2',
                'condicion' => 'el cliente ya entregó al menos un w2',
                'nota_si_no_aplica' => 'Si todavía no entregó ningún w2, no se pregunta.',
            ],
        ];

        foreach ($pasos as $paso) {
            PromptActivoStep::query()->create($paso);
        }
    }
}
