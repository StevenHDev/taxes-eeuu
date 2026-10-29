<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1.1 del documento madre (9.3.1): catálogo fiscal por año, con lo que el
 * IRS define para una persona natural. Tablas nuevas al lado de
 * `catalogo_campos`, que sigue alimentando el formulario y el agente actuales
 * hasta la fase 2.
 *
 * Todas las tablas llevan `tax_year`. Las piezas se apuntan entre sí por
 * código (A1, w2, 1040) dentro del mismo año, no por id, para que copiar un
 * año en otro no obligue a reescribir referencias. Nada se borra: se
 * desactiva con `activo = false`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_anios', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year')->unique();
            $table->string('estado')->default('borrador');
            $table->unsignedSmallInteger('copiado_de')->nullable();
            $table->timestamps();
        });

        Schema::create('catalogo_preguntas', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->string('codigo', 20);
            $table->string('bloque', 2);
            $table->unsignedTinyInteger('pantalla')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->text('texto_es');
            $table->text('texto_en')->nullable();
            $table->text('ayuda_es')->nullable();
            $table->text('ayuda_en')->nullable();
            $table->string('tipo_respuesta');
            $table->json('opciones')->nullable();
            $table->string('nivel');
            $table->boolean('por_persona')->default(false);
            $table->boolean('sensible')->default(false);
            $table->json('condicion')->nullable();
            $this->fuenteIrs($table);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tax_year', 'codigo']);
            $table->index(['tax_year', 'bloque', 'orden']);
        });

        Schema::create('catalogo_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->string('codigo', 40);
            $table->string('grupo');
            $table->string('nombre_oficial');
            $table->string('nombre_sencillo_es');
            $table->string('nombre_sencillo_en')->nullable();
            $table->string('emisor_es')->nullable();
            $table->string('emisor_en')->nullable();
            $table->string('fecha_llegada')->nullable();
            $table->text('si_no_llega_es')->nullable();
            $table->text('si_no_llega_en')->nullable();
            $table->json('formatos')->nullable();
            $table->string('uno_por')->default('unico');
            $table->unsignedSmallInteger('orden')->default(0);
            $this->fuenteIrs($table);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tax_year', 'codigo']);
        });

        Schema::create('catalogo_documento_casillas', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->foreignId('documento_id')->constrained('catalogo_documentos')->restrictOnDelete();
            $table->string('casilla', 40);
            $table->string('descripcion');
            $table->string('tipo_dato')->nullable();
            $table->boolean('clave')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['documento_id', 'casilla']);
        });

        Schema::create('catalogo_formularios', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->string('codigo', 40);
            $table->string('nombre');
            $table->text('para_que')->nullable();
            $table->boolean('siempre')->default(false);
            $table->string('ficha')->default('pendiente');
            $table->unsignedSmallInteger('orden')->default(0);
            $this->fuenteIrs($table);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tax_year', 'codigo']);
        });

        Schema::create('catalogo_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->foreignId('formulario_id')->constrained('catalogo_formularios')->restrictOnDelete();
            $table->string('linea', 40);
            $table->string('descripcion');
            $table->string('origen_tipo');
            // Código de lo que la alimenta, en el mismo año: "w2:1" (casilla),
            // "sch-c:31" (línea), "B1" (respuesta). Nulo si es un cálculo.
            $table->string('origen_clave')->nullable();
            $table->string('campo_pdf')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['formulario_id', 'linea']);
        });

        Schema::create('catalogo_activaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->foreignId('pregunta_id')->constrained('catalogo_preguntas')->restrictOnDelete();
            $table->json('respuesta');
            $table->string('objetivo_tipo');
            $table->string('objetivo_codigo', 40);
            $table->string('nota')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tax_year', 'objetivo_tipo', 'objetivo_codigo']);
        });

        Schema::create('catalogo_reglas', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->string('codigo', 20);
            $table->text('descripcion');
            $table->string('severidad');
            $table->boolean('implementada')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $this->fuenteIrs($table);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tax_year', 'codigo']);
        });

        // Solo se inserta. Guarda el valor anterior y el nuevo: el catálogo
        // no tiene datos de clientes, así que no choca con el criterio de
        // `bitacora_actividad` (que solo guarda nombres de atributos).
        Schema::create('catalogo_cambios', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('campo');
            $table->json('valor_anterior')->nullable();
            $table->json('valor_nuevo')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen');
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['tax_year', 'origen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_cambios');
        Schema::dropIfExists('catalogo_reglas');
        Schema::dropIfExists('catalogo_activaciones');
        Schema::dropIfExists('catalogo_lineas');
        Schema::dropIfExists('catalogo_formularios');
        Schema::dropIfExists('catalogo_documento_casillas');
        Schema::dropIfExists('catalogo_documentos');
        Schema::dropIfExists('catalogo_preguntas');
        Schema::dropIfExists('catalogo_anios');
    }

    /**
     * Fuente del IRS y validación (principio 1 y 7.7). `validado_en` nulo
     * significa "sin validar contra la publicación del IRS".
     */
    private function fuenteIrs(Blueprint $table): void
    {
        $table->text('fuente_texto')->nullable();
        $table->string('fuente_url', 500)->nullable();
        $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('validado_en')->nullable();
    }
};
