<?php

use App\Enums\FaseConversacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt del agente conversacional de WhatsApp, versionado en base de datos —
 * mismo patrón que parametros_fiscales/ParametrosFiscales (ver
 * App\Support\AgentePromptVigente). Cada fila es el bloque de una sola fase
 * de la conversación (FaseConversacion); publicar una versión nueva implica
 * escribir una fila por cada fase con el mismo `version`, aunque el
 * contenido de alguna no haya cambiado — así "la versión vigente" siempre
 * puede resolverse como "la de mayor `version` ya publicada", sin huecos por
 * fase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agente_prompts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version');
            $table->string('fase');
            $table->longText('contenido');
            $table->timestamp('publicada_en')->nullable();
            $table->timestamps();

            $table->unique(['version', 'fase']);
            $table->index('publicada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_prompts');
    }
};
