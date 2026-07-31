<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce tax_year como dimensión de primera clase: los créditos, umbrales y
 * pruebas de dependientes cambian cada año fiscal, así que tanto el catálogo
 * de campos como los datos del cliente deben poder variar por año desde el
 * día uno. Los registros existentes (datos de desarrollo/prueba) quedan en
 * 2025 vía el DEFAULT de columna — no hace falta un UPDATE separado ni
 * doctrine/dbal (no está en composer.json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_campos', function (Blueprint $table) {
            $table->unsignedSmallInteger('tax_year')->default(2025)->after('forma');
        });
        Schema::table('catalogo_campos', function (Blueprint $table) {
            $table->dropUnique(['forma', 'clave']);
            $table->unique(['forma', 'clave', 'tax_year']);
        });

        Schema::table('campos_cliente', function (Blueprint $table) {
            $table->unsignedSmallInteger('tax_year')->default(2025)->after('forma');
        });
        Schema::table('campos_cliente', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'forma', 'campo']);
            $table->unique(['user_id', 'forma', 'campo', 'tax_year']);
        });

        Schema::table('formas_cliente', function (Blueprint $table) {
            $table->unsignedSmallInteger('tax_year')->default(2025)->after('forma');
        });
        Schema::table('formas_cliente', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'forma']);
            $table->unique(['user_id', 'forma', 'tax_year']);
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedSmallInteger('tax_year')->default(2025)->after('forma');
        });
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'forma', 'campo']);
            $table->index(['user_id', 'forma', 'campo', 'tax_year']);
        });

        Schema::table('historial_cambios', function (Blueprint $table) {
            $table->unsignedSmallInteger('tax_year')->default(2025)->after('forma');
        });
        Schema::table('historial_cambios', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'forma', 'campo']);
            $table->index(['user_id', 'forma', 'campo', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::table('historial_cambios', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'forma', 'campo', 'tax_year']);
            $table->index(['user_id', 'forma', 'campo']);
            $table->dropColumn('tax_year');
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'forma', 'campo', 'tax_year']);
            $table->index(['user_id', 'forma', 'campo']);
            $table->dropColumn('tax_year');
        });

        Schema::table('formas_cliente', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'forma', 'tax_year']);
            $table->unique(['user_id', 'forma']);
            $table->dropColumn('tax_year');
        });

        Schema::table('campos_cliente', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'forma', 'campo', 'tax_year']);
            $table->unique(['user_id', 'forma', 'campo']);
            $table->dropColumn('tax_year');
        });

        Schema::table('catalogo_campos', function (Blueprint $table) {
            $table->dropUnique(['forma', 'clave', 'tax_year']);
            $table->unique(['forma', 'clave']);
            $table->dropColumn('tax_year');
        });
    }
};
