<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F4.1: una casilla es "sensible" si trae un SSN, EIN o TIN, el nombre de
     * una persona u otro dato personal. El lector de F4 la guarda cifrada y
     * nunca la muestra en logs ni reportes.
     */
    public function up(): void
    {
        Schema::table('catalogo_documento_casillas', function (Blueprint $table) {
            $table->boolean('sensible')->default(false)->after('clave');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_documento_casillas', function (Blueprint $table) {
            $table->dropColumn('sensible');
        });
    }
};
