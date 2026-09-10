<?php

namespace Database\Seeders;

use App\Enums\FaseConversacion;
use App\Models\AgentePrompt;
use App\Support\AgentePromptVigente;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Siembra la versión 1 (ya publicada) del prompt del agente de WhatsApp,
 * leyendo el contenido adaptado por fase desde `prompt_actuales/fases/*.md`
 * — ver docs/implementar_agente_n8n.md. Idempotente: puede re-ejecutarse
 * para refrescar el contenido de la version=1 si esos archivos cambian,
 * antes de publicar una version=2 real vía el flujo normal de versionado.
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
