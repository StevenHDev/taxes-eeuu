<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.2: el checklist de documentos guardado. Un ítem por documento activado,
 * por persona (bloque C) y por fuente ("2 empleos" son dos W-2), más los
 * "otro documento" del cliente. Guarda el estado (5.3), el motivo de "no
 * aplica", la observación del preparador y quién lo revisó. Un ítem que deja
 * de aplicar no se borra: queda con `retirado_en` y sus archivos se
 * conservan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevista_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrevista_id')->constrained('entrevistas')->cascadeOnDelete();
            $table->string('documento_codigo', 60);
            $table->foreignId('persona_id')->nullable()->constrained('entrevista_personas')->nullOnDelete();
            $table->unsignedSmallInteger('numero')->default(1);
            // Lo agregó el cliente ("otro banco", "otro empleo" u "otro
            // documento"): la sincronización con las respuestas no lo retira.
            $table->boolean('agregado_por_cliente')->default(false);
            $table->string('descripcion')->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->text('motivo_no_aplica')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamp('retirado_en')->nullable();
            $table->timestamps();

            $table->unique(['entrevista_id', 'documento_codigo', 'persona_id', 'numero'], 'entrevista_documentos_unico');
            $table->index(['entrevista_id', 'estado']);
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('entrevista_documento_id')->nullable()->after('persona_id')
                ->constrained('entrevista_documentos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrevista_documento_id');
        });

        Schema::dropIfExists('entrevista_documentos');
    }
};
