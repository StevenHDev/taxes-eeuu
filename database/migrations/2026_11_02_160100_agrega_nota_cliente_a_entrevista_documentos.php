<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.7: la nota del cliente en un documento de registros propios (meses que
 * una propiedad estuvo vacía, cobros o gastos en efectivo sin recibo). No
 * cambia el estado del ítem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrevista_documentos', function (Blueprint $table) {
            $table->text('nota_cliente')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('entrevista_documentos', function (Blueprint $table) {
            $table->dropColumn('nota_cliente');
        });
    }
};
