<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2.6: los documentos que pide la entrevista.
 * - `documentos`: un archivo de la entrevista se identifica por el código
 *   del documento del catálogo (`documento_codigo`) y, en el bloque C, por
 *   la persona. `forma` y `campo` (formulario actual) pasan a ser opcionales;
 *   los documentos viejos los conservan.
 * - `catalogo_documentos.siempre`: lo que se pide a todos aunque ninguna
 *   respuesta lo active (la identificación con foto), igual que
 *   `catalogo_formularios.siempre` con el 1040.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('forma')->nullable()->change();
            $table->string('campo')->nullable()->change();
            $table->string('documento_codigo', 60)->nullable()->after('campo');
            $table->foreignId('entrevista_id')->nullable()->after('documento_codigo')->constrained('entrevistas')->nullOnDelete();
            $table->foreignId('persona_id')->nullable()->after('entrevista_id')->constrained('entrevista_personas')->nullOnDelete();

            $table->index(['entrevista_id', 'documento_codigo']);
        });

        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->boolean('siempre')->default(false)->after('uno_por');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->dropColumn('siempre');
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['entrevista_id', 'documento_codigo']);
            $table->dropConstrainedForeignId('persona_id');
            $table->dropConstrainedForeignId('entrevista_id');
            $table->dropColumn('documento_codigo');
            $table->string('forma')->nullable(false)->change();
            $table->string('campo')->nullable(false)->change();
        });
    }
};
