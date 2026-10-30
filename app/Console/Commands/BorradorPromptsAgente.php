<?php

namespace App\Console\Commands;

use App\Enums\FaseConversacion;
use App\Models\AgentePrompt;
use App\Support\AgentePromptVigente;
use Illuminate\Console\Command;

/**
 * Prepara un BORRADOR de prompts del agente (F2.8) sin publicarlo: copia la
 * versión vigente (con lo que ya se haya editado desde "Agente → Prompts")
 * y reemplaza solo las fases pedidas con su archivo de
 * `prompt_actuales/fases`. Las fases que la vigente no tiene (las nuevas)
 * también salen de su archivo. Un administrador lo revisa y lo publica
 * desde "Agente → Prompts".
 */
class BorradorPromptsAgente extends Command
{
    protected $signature = 'agente:borrador-prompts
        {--fases= : Fases a tomar del archivo, separadas por coma (además de las que la versión vigente no tiene)}';

    protected $description = 'Prepara un borrador de prompts del agente desde prompt_actuales/fases, sin publicarlo';

    public function handle(): int
    {
        $pedidas = array_filter(array_map('trim', explode(',', (string) $this->option('fases'))));
        $validas = array_map(fn (FaseConversacion $f) => $f->value, FaseConversacion::cases());

        if (($invalidas = array_diff($pedidas, $validas)) !== []) {
            $this->error('Fases desconocidas: '.implode(', ', $invalidas));

            return self::FAILURE;
        }

        $vigente = AgentePromptVigente::version();
        $maxima = (int) AgentePrompt::query()->max('version');
        $borrador = $maxima > (int) $vigente ? $maxima : (int) $vigente + 1;
        $filas = [];

        foreach (FaseConversacion::cases() as $fase) {
            $actual = AgentePromptVigente::paraFase($fase);
            $desdeArchivo = in_array($fase->value, $pedidas, true) || $actual === null;
            $ruta = base_path("prompt_actuales/fases/{$fase->value}.md");

            if ($desdeArchivo && ! is_file($ruta)) {
                $this->error("Falta el archivo {$ruta}.");

                return self::FAILURE;
            }

            AgentePrompt::query()->updateOrCreate(
                ['version' => $borrador, 'fase' => $fase->value],
                ['contenido' => $desdeArchivo ? file_get_contents($ruta) : $actual],
            );

            $filas[] = [$fase->value, $desdeArchivo ? 'archivo' : "versión {$vigente}"];
        }

        $this->table(['Fase', 'Contenido'], $filas);
        $this->info("Borrador {$borrador} listo, sin publicar. Revísalo y publícalo desde Agente → Prompts.");

        return self::SUCCESS;
    }
}
