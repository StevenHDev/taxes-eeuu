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
            ['orden' => 2, 'tipo' => TipoPromptActivoStep::Simple, 'campo' => 'estado_civil'],
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
        ];

        foreach ($pasos as $paso) {
            PromptActivoStep::query()->create($paso);
        }
    }
}
