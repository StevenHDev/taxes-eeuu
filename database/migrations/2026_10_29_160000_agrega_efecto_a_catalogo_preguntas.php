<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1.3:
 * - La columna "Activa / descarta" (o "Va a") de cada pregunta de la
 *   sección 3, tal cual, para que el preparador la lea en la pantalla. Las
 *   activaciones estructuradas siguen en `catalogo_activaciones`.
 * - `nombre_oficial` de un documento pasa a opcional: la identificación con
 *   foto o un recibo no son formularios del IRS. El nombre que se muestra
 *   es `nombre_sencillo_es`, que sigue siendo obligatorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_preguntas', function (Blueprint $table) {
            $table->text('efecto_es')->nullable()->after('condicion');
        });

        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->string('nombre_oficial')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_preguntas', function (Blueprint $table) {
            $table->dropColumn('efecto_es');
        });

        Schema::table('catalogo_documentos', function (Blueprint $table) {
            $table->string('nombre_oficial')->nullable(false)->change();
        });
    }
};
