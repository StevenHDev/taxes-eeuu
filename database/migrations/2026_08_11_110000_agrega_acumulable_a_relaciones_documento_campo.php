<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encontrado en pruebas end-to-end (dos documentos revelando el mismo
 * campo/subcampo, ej. 1099-INT y 1099-DIV ambos hacia
 * `ingresos.intereses_dividendos`): la relación `revela` solo se aplicaba
 * una vez — el segundo documento se descartaba en silencio en vez de
 * sumarse. `acumulable` marca qué relaciones deben SUMAR en vez de
 * sobrescribir cuando el agente ya resolvió ese destino con otro documento —
 * ver EventoRecoleccionService::procesar() y el parámetro `acumular` de
 * POST /api/eventos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relaciones_documento_campo', function (Blueprint $table) {
            $table->boolean('acumulable')->default(false)->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('relaciones_documento_campo', function (Blueprint $table) {
            $table->dropColumn('acumulable');
        });
    }
};
