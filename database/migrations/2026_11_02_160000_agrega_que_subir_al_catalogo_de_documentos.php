<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.7: qué subir cuando no hay formulario (registros propios de ingresos,
 * gastos y alquileres) y el nombre de la plantilla opcional que se ofrece
 * (`public/plantillas/{plantilla}-{es|en}.{xlsx|pdf}`). Solo lo llenan esos
 * dos documentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->text('que_subir_es')->nullable()->after('si_no_llega_en');
            $table->text('que_subir_en')->nullable()->after('que_subir_es');
            $table->string('plantilla', 60)->nullable()->after('que_subir_en');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->dropColumn(['que_subir_es', 'que_subir_en', 'plantilla']);
        });
    }
};
