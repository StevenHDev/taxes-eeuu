<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6 6.3b: cómo se lee un documento del catálogo. `casillas` (por defecto)
 * es lo de siempre: una casilla fija por dato. `renglones` es para los
 * documentos que traen una lista de movimientos (los registros de ingresos y
 * gastos del negocio): se leen por renglones y la plataforma los suma por
 * categoría (App\Services\Lectura\SumasRegistros).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->string('lectura', 12)->default('casillas')->after('uno_por');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->dropColumn('lectura');
        });
    }
};
