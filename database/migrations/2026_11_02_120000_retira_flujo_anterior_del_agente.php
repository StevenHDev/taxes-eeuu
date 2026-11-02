<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2.11: retiro del flujo anterior.
 * - `prompt_activo_steps` (E6, árbol de 44 pasos): es configuración, no
 *   datos de clientes; se borra.
 * - `agente_tool_estados`: se borran las filas de las fases retiradas
 *   (determinacion_formas, recoleccion, cierre, handoff_portal). Las filas
 *   viejas de `agente_prompts` quedan como historial.
 * - `users.entrevista_catalogo`: todos los clientes usan la entrevista.
 */
return new class extends Migration
{
    private const FASES_RETIRADAS = ['determinacion_formas', 'recoleccion', 'cierre', 'handoff_portal'];

    public function up(): void
    {
        DB::table('agente_tool_estados')->whereIn('fase', self::FASES_RETIRADAS)->delete();

        Schema::dropIfExists('prompt_activo_steps');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('entrevista_catalogo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('entrevista_catalogo')->default(false)->after('es_prueba');
        });

        // Solo la estructura: el contenido del árbol vivía en
        // PromptActivoStepsSeeder, que también se retiró.
        Schema::create('prompt_activo_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('orden');
            $table->string('tipo');
            $table->string('campo')->nullable();
            $table->string('condicion')->nullable();
            $table->string('nota_si_no_aplica')->nullable();
            $table->text('nota')->nullable();
            $table->string('pregunta')->nullable();
            $table->string('campo_si')->nullable();
            $table->string('campo_no')->nullable();
            $table->string('etiqueta')->nullable();
            $table->json('miembros')->nullable();
            $table->timestamps();
        });
    }
};
