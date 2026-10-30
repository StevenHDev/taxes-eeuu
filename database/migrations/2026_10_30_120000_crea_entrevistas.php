<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2.2: respuestas de la entrevista por cliente y año, sobre el catálogo
 * fiscal (F1). `campos_cliente` no se toca: convive hasta la migración de
 * 2.10.
 *
 * - Las respuestas guardan el código de la pregunta, no su id: condiciones y
 *   activaciones ya trabajan por código del año, y las piezas del catálogo
 *   nunca se borran.
 * - `valor` va siempre cifrado (`encrypted:array`), como `campos_cliente`.
 * - Una respuesta por pregunta, o por pregunta y persona en el bloque C: dos
 *   índices únicos parciales (PostgreSQL y SQLite los aceptan igual).
 * - `consentimiento_id` queda sin llave foránea hasta que exista la tabla
 *   del consentimiento §7216 (2.4).
 * - `users.entrevista_catalogo`: la bandera por cliente hasta 2.10; se borra
 *   en 2.11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->string('estado', 20);
            $table->string('canal_inicio', 20);
            $table->timestamp('iniciada_en');
            $table->timestamp('confirmada_en')->nullable();
            $table->unsignedBigInteger('consentimiento_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tax_year']);
        });

        Schema::create('entrevista_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrevista_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('etiqueta', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entrevista_id', 'orden']);
        });

        Schema::create('entrevista_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrevista_id')->constrained()->cascadeOnDelete();
            $table->string('pregunta_codigo', 20);
            $table->foreignId('persona_id')->nullable()->constrained('entrevista_personas')->cascadeOnDelete();
            $table->text('valor');
            $table->string('canal', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('respondida_en');
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX entrevista_respuestas_sin_persona_unique ON entrevista_respuestas (entrevista_id, pregunta_codigo) WHERE persona_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX entrevista_respuestas_con_persona_unique ON entrevista_respuestas (entrevista_id, pregunta_codigo, persona_id) WHERE persona_id IS NOT NULL');

        Schema::create('entrevista_respuesta_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('entrevista_respuestas')->cascadeOnDelete();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo');
            $table->string('canal', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('entrevista_catalogo')->default(false)->after('es_prueba');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('entrevista_catalogo');
        });

        Schema::dropIfExists('entrevista_respuesta_cambios');
        Schema::dropIfExists('entrevista_respuestas');
        Schema::dropIfExists('entrevista_personas');
        Schema::dropIfExists('entrevistas');
    }
};
