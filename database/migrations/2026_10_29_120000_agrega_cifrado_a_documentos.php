<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F0.2: los archivos nuevos se guardan cifrados. Los que ya existen quedan
 * en `false` hasta correr `php artisan documentos:cifrar-existentes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->boolean('cifrado')->default(false)->after('hash_contenido');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('cifrado');
        });
    }
};
