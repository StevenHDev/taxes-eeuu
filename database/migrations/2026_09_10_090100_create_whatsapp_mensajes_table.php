<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de la conversación de WhatsApp — reemplaza la lectura de solo
 * lectura que hoy hace SupabaseWhatsappConversationService contra
 * globaltax_registro_whatsapp (ver MigrarHistoricoWhatsappSupabase para la
 * migración del histórico existente).
 *
 * `cliente_id` es nullable porque un mensaje puede llegar antes de que exista
 * cuenta (fase de verificación) — se identifica por `telefono` mientras tanto.
 * `twilio_message_sid` es nullable+único: los mensajes salientes escritos
 * manualmente por un preparador (ver EstadoControlConversacion) también
 * quedan con su propio SID una vez enviados por Twilio, pero la columna
 * admite null para cualquier caso en que el envío no pase por Twilio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->string('telefono');
            $table->foreignId('cliente_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rol');
            $table->text('contenido');
            $table->string('twilio_message_sid')->nullable()->unique();
            // Número de versión de agente_prompts vigente cuando se generó este
            // mensaje (no una FK a una sola fila: una versión abarca varias filas,
            // una por fase — ver create_agente_prompts_table). Null en mensajes que
            // no genera el agente (del cliente, o escritos manualmente).
            $table->unsignedInteger('prompt_version')->nullable();
            $table->timestamps();

            $table->index(['telefono', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensajes');
    }
};
