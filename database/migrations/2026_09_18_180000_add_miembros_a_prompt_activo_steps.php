<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0 del plan de cierre de brecha GTS (ver docs/... y el artifact
 * "Matriz GTS 1040"): agrega el tipo de paso `grupo` a
 * `TipoPromptActivoStep`, que generaliza lo que `bifurcacion` ya resuelve
 * para 2 ramas a N miembros — una sola pregunta compuesta que resuelve
 * varios campos distintos según cuáles confirme el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompt_activo_steps', function (Blueprint $table) {
            // Solo 'grupo': lista de nombres de campo que agrupa (ej.
            // ['form_1099_int', 'form_1099_div']). La pregunta compuesta en
            // sí reusa las columnas 'etiqueta'/'pregunta' que ya existen
            // para 'bifurcacion'.
            $table->json('miembros')->nullable()->after('etiqueta');
        });
    }

    public function down(): void
    {
        Schema::table('prompt_activo_steps', function (Blueprint $table) {
            $table->dropColumn('miembros');
        });
    }
};
