<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F6 6.5: el respaldo manual sin documento (último recurso) y su historial.
 * El valor va cifrado; el motivo es texto del preparador y nunca sale en logs,
 * bitácora ni excepciones. No se borra nada: retirar y reemplazar marcan la fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_datos_sin_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            // `documento:casilla` o `registros-ingresos-gastos:<línea del Schedule C>`.
            $table->string('clave', 120);
            $table->foreignId('persona_id')->nullable()->constrained('entrevista_personas')->nullOnDelete();
            $table->text('valor');
            $table->text('motivo');
            $table->string('fuente', 20);
            $table->date('fuente_fecha')->nullable();
            $table->string('fuente_canal', 20)->nullable();
            $table->foreignId('anotado_por')->nullable()->constrained('users')->nullOnDelete();
            // El documento leído llegó después: el manual deja de contar, pero se conserva.
            $table->timestamp('reemplazado_en')->nullable();
            $table->timestamp('retirado_en')->nullable();
            $table->foreignId('retirado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expediente_id', 'clave']);
        });

        // Uno vigente por expediente + clave + persona. Con COALESCE porque dos NULL no chocan en un índice único.
        DB::statement('CREATE UNIQUE INDEX expediente_datos_sin_documento_vigente_unique ON expediente_datos_sin_documento (expediente_id, clave, COALESCE(persona_id, 0)) WHERE reemplazado_en IS NULL AND retirado_en IS NULL');

        Schema::create('expediente_dato_sin_documento_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dato_id')->constrained('expediente_datos_sin_documento')->cascadeOnDelete();
            // anotado, cambiado, retirado o reemplazado (por el documento leído).
            $table->string('accion', 20);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->text('motivo')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_dato_sin_documento_cambios');
        Schema::dropIfExists('expediente_datos_sin_documento');
    }
};
