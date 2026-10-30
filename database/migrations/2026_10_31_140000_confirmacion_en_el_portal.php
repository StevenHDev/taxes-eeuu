<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2.7: el cliente confirma su entrevista en el portal. La confirmación se
 * guarda en la misma tabla de atestaciones (se conserva, 9.3.2; solo se
 * inserta), con el canal, la entrevista y un hash de las respuestas que
 * confirmó. Las atestaciones viejas, hechas por WhatsApp, quedan con
 * `canal = whatsapp` y sin entrevista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_atestaciones', function (Blueprint $table) {
            $table->string('canal', 20)->default('whatsapp')->after('tax_year');
            $table->foreignId('entrevista_id')->nullable()->after('canal')->constrained('entrevistas')->nullOnDelete();
            $table->string('resumen_hash', 64)->nullable()->after('respuesta_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('cliente_atestaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrevista_id');
            $table->dropColumn(['canal', 'resumen_hash']);
        });
    }
};
