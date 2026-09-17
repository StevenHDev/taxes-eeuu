<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guardarraíl de calidad de datos: cuando un campo tipo número se guarda con
 * el mismo valor exacto que otro campo ya guardado del mismo cliente/año
 * fiscal, es una señal fuerte de que la extracción confundió dos casillas
 * distintas de un documento (caso real: un W-2 cuyo `deducciones` terminó
 * siendo un duplicado exacto de `impuestos_retenidos`, el mismo número de
 * Box 2 reutilizado por error). No bloquea el guardado ni cambia `estado` —
 * solo deja una nota visible para el preparador. Ver
 * EventoRecoleccionService::detectarValorDuplicado().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_cliente', function (Blueprint $table) {
            $table->text('advertencia')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('campos_cliente', function (Blueprint $table) {
            $table->dropColumn('advertencia');
        });
    }
};
