<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Support\AgentePromptVigente;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentePromptsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_las_4_fases_como_version_1_publicada(): void
    {
        $this->seed(AgentePromptsSeeder::class);

        $this->assertSame(1, AgentePromptVigente::version());

        foreach (FaseConversacion::cases() as $fase) {
            $ruta = base_path("prompt_actuales/fases/{$fase->value}.md");

            $this->assertSame(
                file_get_contents($ruta),
                AgentePromptVigente::paraFase($fase),
                "El contenido sembrado para [{$fase->value}] no coincide con el archivo fuente.",
            );
        }
    }

    public function test_es_idempotente_y_refresca_el_contenido_si_el_archivo_cambio(): void
    {
        $this->seed(AgentePromptsSeeder::class);
        $this->seed(AgentePromptsSeeder::class);

        $this->assertSame(1, AgentePromptVigente::version());
        $this->assertDatabaseCount('agente_prompts', count(FaseConversacion::cases()));
    }
}
