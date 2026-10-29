<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F0.3 (documento madre, T4): los clientes y sus documentos se archivan en
 * vez de borrarse, porque el IRS exige conservarlos 3 años (§6107, Form
 * 8867). `es_prueba` marca los usuarios de prueba, los únicos que el
 * administrador puede eliminar definitivamente (decisión de Steven, 28 oct.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_prueba')->default(false)->after('preparer_id');
            $table->softDeletes();
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('es_prueba');
        });
    }
};
