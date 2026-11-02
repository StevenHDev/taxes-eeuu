<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2.10: cuándo se migró la entrevista desde `campos_cliente`. La migración
 * salta las entrevistas que ya lo tienen, así se puede correr de nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->timestamp('migrada_en')->nullable()->after('confirmada_en');
        });
    }

    public function down(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->dropColumn('migrada_en');
        });
    }
};
