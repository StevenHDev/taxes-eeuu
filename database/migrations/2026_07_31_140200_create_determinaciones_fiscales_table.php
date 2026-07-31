<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultados calculados por el motor de reglas (filing status, calificación
 * de dependientes, AGI, créditos) — separados a propósito de `campos_cliente`
 * (que es lo que el cliente/agente entregó) para no mezclar "lo que dijo el
 * cliente" con "lo que calculó el sistema". `version_reglas` deja trazabilidad
 * de qué versión de parámetros produjo cada resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('determinaciones_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->string('tipo');
            // 'text', no 'json': el cast `encrypted:array` guarda un string
            // cifrado, no JSON plano — ver nota idéntica en campos_cliente.
            $table->text('resultado');
            $table->string('version_reglas');
            $table->timestamp('calculado_en');
            $table->timestamps();

            $table->unique(['user_id', 'tax_year', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('determinaciones_fiscales');
    }
};
