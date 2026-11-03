<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4.2: lo que se lee de cada archivo (documento madre, 6.1) y lo que no
 * pasa las reglas (6.3).
 *
 * - `documento_lecturas`: un intento de lectura de un archivo.
 * - `documento_datos`: un dato por casilla del catálogo, con su confianza y
 *   su evidencia. El valor va cifrado (es información de la declaración,
 *   §7216); el valor leído se conserva aunque el preparador lo corrija.
 * - `documento_dato_cambios`: historial de correcciones; solo se inserta.
 * - `excepciones`: una por regla que falla, sin valores en claro.
 *
 * Nada se borra a mano: se va con el archivo cuando vence su plazo de
 * conservación (Documento es Prunable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->foreignId('entrevista_documento_id')->nullable()->constrained('entrevista_documentos')->nullOnDelete();
            $table->string('lector', 30);
            $table->string('modelo', 80)->nullable();
            $table->string('estado', 20)->index();
            $table->string('tipo_detectado', 40)->nullable();
            $table->unsignedSmallInteger('anio_detectado')->nullable();
            $table->unsignedSmallInteger('paginas')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('terminada_en')->nullable();
            $table->timestamps();
        });

        Schema::create('documento_datos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lectura_id')->constrained('documento_lecturas')->cascadeOnDelete();
            $table->foreignId('entrevista_documento_id')->nullable()->constrained('entrevista_documentos')->nullOnDelete();
            $table->string('casilla', 40);
            $table->text('valor')->nullable();
            $table->text('valor_leido')->nullable();
            $table->decimal('confianza', 3, 2)->nullable();
            $table->string('legibilidad', 12)->nullable();
            $table->unsignedSmallInteger('pagina')->nullable();
            $table->json('recuadro')->nullable();
            $table->text('evidencia')->nullable();
            $table->string('origen', 12);
            $table->foreignId('corregido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corregido_en')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->unique(['lectura_id', 'casilla']);
            $table->index('entrevista_documento_id');
        });

        Schema::create('documento_dato_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dato_id')->constrained('documento_datos')->cascadeOnDelete();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->text('motivo');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('excepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrevista_id')->constrained('entrevistas')->cascadeOnDelete();
            $table->foreignId('entrevista_documento_id')->nullable()->constrained('entrevista_documentos')->nullOnDelete();
            $table->foreignId('documento_dato_id')->nullable()->constrained('documento_datos')->nullOnDelete();
            $table->string('regla_codigo', 10);
            $table->string('severidad', 12);
            $table->string('clave', 120);
            $table->json('detalle')->nullable();
            $table->string('estado', 12)->index();
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_en')->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->unique(['entrevista_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excepciones');
        Schema::dropIfExists('documento_dato_cambios');
        Schema::dropIfExists('documento_datos');
        Schema::dropIfExists('documento_lecturas');
    }
};
