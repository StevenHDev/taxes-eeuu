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
        ];

        foreach ($pasos as $paso) {
            PromptActivoStep::query()->create($paso);
        }
    }
}
