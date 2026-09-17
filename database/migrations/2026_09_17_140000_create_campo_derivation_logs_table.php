<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traza de cada documento con relaciones documento→campo declaradas
 * (ver App\Models\RelacionDocumentoCampo): qué se esperaba que revelara según
 * el catálogo, qué llegó realmente en `revelados` en esa misma invocación de
 * guardar_campo_cliente, y cuáles relaciones declaradas quedaron sin
 * cubrir. Análogo a `agent_derivation_logs` del agente de salud (Natalia) —
 * ver agentes-subagentes-flujos-motor-decision.md — pensado para depurar
 * "por qué no se guardó tal campo desde tal documento" sin tener que leer
 * el historial completo de la conversación a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campo_derivation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->string('documento_campo');
            $table->json('relaciones_esperadas');
            $table->json('revelados_recibidos');
            $table->json('relaciones_faltantes');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campo_derivation_logs');
    }
};
