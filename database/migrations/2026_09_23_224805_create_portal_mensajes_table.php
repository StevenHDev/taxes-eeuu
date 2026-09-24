<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial del chat del portal seguro (Fase 2 del portal de documentos
 * sensibles — ver App\Contracts\MensajeConversacion): mismo motor
 * conversacional que whatsapp_mensajes, pero sin `telefono` — acceder al
 * portal ya requiere sesión autenticada, así que `cliente_id` nunca es null
 * (a diferencia de whatsapp_mensajes, donde puede llegar un mensaje antes de
 * que exista cuenta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->string('rol');
            $table->text('contenido');
            // Igual que whatsapp_mensajes.prompt_version: null en el mensaje
            // del cliente, con valor en el del agente.
            $table->unsignedInteger('prompt_version')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_mensajes');
    }
};
