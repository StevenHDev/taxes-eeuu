<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuente del IRS y validación por línea (F6, 6.0): cada línea que se calcula
 * dice de qué formulario o instrucción sale y en qué página, y se marca
 * "validada contra el IRS" solo cuando hay un caso de prueba que la cubre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_lineas', function (Blueprint $table) {
            $table->text('fuente_texto')->nullable();
            $table->string('fuente_url', 500)->nullable();
            $table->string('fuente_pagina', 80)->nullable();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_lineas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validado_por');
            $table->dropColumn(['fuente_texto', 'fuente_url', 'fuente_pagina', 'validado_en']);
        });
    }
};
