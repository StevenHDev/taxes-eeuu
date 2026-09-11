<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte de más de un proveedor de WhatsApp (Twilio y Meta Cloud API — ver
 * App\Services\Whatsapp\WhatsappChannel): la columna ya no puede llamarse
 * `twilio_message_sid` cuando puede contener el id de un mensaje de Meta, y
 * se agrega `proveedor` para trazabilidad de cuál atendió cada mensaje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            $table->renameColumn('twilio_message_sid', 'mensaje_externo_id');
            $table->string('proveedor')->nullable()->after('mensaje_externo_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            $table->dropColumn('proveedor');
            $table->renameColumn('mensaje_externo_id', 'twilio_message_sid');
        });
    }
};
