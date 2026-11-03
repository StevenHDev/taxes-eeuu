<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4.8b: la miniatura del formulario oficial del IRS vacío que se muestra en
 * la fila del documento (`public/{imagen_ejemplo}`). Solo la llenan los
 * documentos que son formularios del IRS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->string('imagen_ejemplo', 120)->nullable()->after('plantilla');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->dropColumn('imagen_ejemplo');
        });
    }
};
