<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activo/inactivo de una tool del agente conversacional para una fase
 * puntual (App\Enums\FaseConversacion) — ver App\Support\AgenteToolEstados.
 * A diferencia de agente_prompts, esto no es un catálogo versionado: el
 * catálogo de tools (nombre, descripción, parámetros) sigue siendo código
 * (App\Services\WhatsappAgent\ToolDefinitions), esta tabla solo apaga
 * puntualmente una tool ya existente para una fase. La ausencia de fila
 * equivale a activo=true, así que una instalación nueva (tabla vacía) se
 * comporta exactamente igual que antes de que existiera este panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agente_tool_estados', function (Blueprint $table) {
            $table->id();
            $table->string('fase');
            $table->string('tool_name');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['fase', 'tool_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_tool_estados');
    }
};
