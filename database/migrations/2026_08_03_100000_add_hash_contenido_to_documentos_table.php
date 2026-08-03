<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hash sha256 (hex, 64 caracteres) del contenido del archivo — permite detectar
 * el mismo documento reutilizado entre clientes distintos (señal de fraude),
 * algo que `unico_por_cliente` no cubre (ese flag es por campo y por cliente,
 * nunca compara bytes). Nullable: no se hace backfill retroactivo de
 * documentos ya existentes. Índice simple, no único — los duplicados se
 * detectan, no se rechazan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('hash_contenido', 64)->nullable()->index()->after('formato');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('hash_contenido');
        });
    }
};
