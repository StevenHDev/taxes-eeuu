<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de control (agente|humano) por conversación de WhatsApp — ver
 * sección ESCALAMIENTO A HUMANO de docs/implementar_agente_n8n.md. Se
 * indexa por teléfono (no por cliente_id) porque un preparador puede
 * necesitar tomar control antes de que exista cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_control', function (Blueprint $table) {
            $table->id();
            $table->string('telefono')->unique();
            $table->foreignId('cliente_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado')->default('agente');
            $table->foreignId('tomado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tomado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_control');
    }
};
