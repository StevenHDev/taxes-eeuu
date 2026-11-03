<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4.3c: por qué cada dato leído tiene la confianza que tiene (en el texto
 * del PDF, claro, dudoso, formato incorrecto…). El número solo no le dice al
 * preparador qué hacer; el motivo sí (App\Enums\Lectura\MotivoConfianza).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_datos', function (Blueprint $table) {
            $table->string('motivo_confianza', 20)->nullable()->after('confianza');
        });
    }

    public function down(): void
    {
        Schema::table('documento_datos', function (Blueprint $table) {
            $table->dropColumn('motivo_confianza');
        });
    }
};
