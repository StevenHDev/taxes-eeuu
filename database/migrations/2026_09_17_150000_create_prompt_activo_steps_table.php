<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orden y condiciones de los campos ACTIVOS de "CAMPOS TRANSVERSALES:
 * ACTIVOS VS. PASIVOS" (prompt_actuales/fases/recoleccion.md), sacados de
 * texto fijo a config editable — análogo a `agents.flow_config` del agente
 * de salud (Natalia), ver agentes-subagentes-flujos-motor-decision.md.
 * Compilado a texto por App\Services\WhatsappAgent\ActivosPromptComposer,
 * consumido por AgentePromptsSeeder al publicar (ver esa clase para el
 * porqué de cada columna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_activo_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('orden');
            // 'simple' (un campo, sin condición), 'condicional' (un campo que
            // solo se pregunta si otro campo ya respondido cumple una
            // condición), 'bifurcacion' (una pregunta conversacional sin
            // campo propio en el catálogo que resuelve DOS campos
            // complementarios, ej. w2/form_1099_nec), 'documento_con_nota'
            // (un campo tipo documento cuya forma de pedirlo ya está
            // definida en otra parte del prompt, ej. form_1095_a).
            $table->string('tipo');
            // Para 'simple'/'condicional'/'documento_con_nota': el campo del
            // catálogo. Para 'bifurcacion': null (no es un campo real).
            $table->string('campo')->nullable();
            // Solo 'condicional': descripción en lenguaje natural de la
            // condición (ej. "estado_civil indica que el cliente es
            // casado"), y qué decir cuando no aplica (ej. "Si es soltero, no
            // se pregunta.").
            $table->string('condicion')->nullable();
            $table->string('nota_si_no_aplica')->nullable();
            // 'simple'/'condicional'/'documento_con_nota': nota libre que se
            // agrega después del nombre del campo (ej. la enumeración de los
            // 10 subcampos de info_dependientes, o la referencia a la lógica
            // de form_1095_a ya definida antes en el prompt).
            $table->text('nota')->nullable();
            // Solo 'bifurcacion': la pregunta conversacional y los dos
            // campos complementarios que resuelve (ver Empleo → w2/1099-NEC).
            $table->string('pregunta')->nullable();
            $table->string('campo_si')->nullable();
            $table->string('campo_no')->nullable();
            // Etiqueta legible cuando no hay un campo real (solo bifurcacion,
            // ej. "Empleo").
            $table->string('etiqueta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_activo_steps');
    }
};
