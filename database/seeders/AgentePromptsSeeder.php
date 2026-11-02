<?php

namespace Database\Seeders;

use App\Enums\FaseConversacion;
use App\Models\AgentePrompt;
use App\Support\AgentePromptVigente;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Siembra la versión 1 (ya publicada) del prompt del agente, leyendo el
 * contenido por fase desde `prompt_actuales/fases/*.md` — ver
 * docs/implementar_agente_n8n.md. Idempotente: puede re-ejecutarse para
 * refrescar la version=1 en un entorno nuevo; en producción las versiones
 * nuevas se crean con `agente:borrador-prompts` y se publican desde
 * Agente → Prompts.
 */
class AgentePromptsSeeder extends Seeder
{
    private const VERSION = 1;

    public function run(): void
    {
        foreach (FaseConversacion::cases() as $fase) {
            $ruta = base_path("prompt_actuales/fases/{$fase->value}.md");

            throw_if(
                ! is_file($ruta),
                new RuntimeException("Falta el archivo de prompt para la fase [{$fase->value}]: {$ruta}"),
            );

            $contenido = file_get_contents($ruta);

            throw_if($contenido === false, new RuntimeException("No se pudo leer el archivo de prompt: {$ruta}"));

            AgentePrompt::query()->updateOrCreate(
                ['version' => self::VERSION, 'fase' => $fase->value],
                ['contenido' => $contenido, 'publicada_en' => now()],
            );
        }

        AgentePromptVigente::invalidate();
    }
}
