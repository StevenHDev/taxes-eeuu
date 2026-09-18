<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reportes del meta-agente: audita conversaciones de WhatsApp YA ocurridas
 * contra el prompt vigente en ese momento (ver App\Support\AgentePromptVigente)
 * y el catálogo, buscando divergencias explícitas (preguntas fuera de orden,
 * reglas violadas, campos preguntados sin existir en el catálogo, promesas
 * sin mecanismo real detrás) — nunca actúa por su cuenta, solo deja
 * hallazgos para revisión humana (ver agentes-subagentes-flujos-motor-decision.md,
 * idea del "meta-agente" con aprobación humana obligatoria).
 *
 * `cliente_id` es nullable: una conversación puede analizarse antes de que
 * exista cuenta (fase VerificacionCuenta) — mismo caso ya cubierto por
 * WhatsappMensaje.cliente_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_agente_reportes', function (Blueprint $table) {
            $table->id();
            $table->string('telefono');
            $table->foreignId('cliente_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rango_desde');
            $table->timestamp('rango_hasta');
            $table->unsignedInteger('mensajes_analizados');
            $table->unsignedInteger('prompt_version')->nullable();
            $table->string('modelo');
            $table->json('hallazgos');
            $table->string('origen');
            $table->foreignId('disparado_por_usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['telefono', 'rango_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_agente_reportes');
    }
};
