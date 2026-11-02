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

    public function test_siembra_todas_las_fases_como_version_1_publicada(): void
    {
        $this->seed(AgentePromptsSeeder::class);

        $this->assertSame(1, AgentePromptVigente::version());

        foreach (FaseConversacion::cases() as $fase) {
            $this->assertSame(
                file_get_contents(base_path("prompt_actuales/fases/{$fase->value}.md")),
                AgentePromptVigente::paraFase($fase),
                "El contenido sembrado para [{$fase->value}] no coincide con su archivo.",
            );
        }
    }

    public function test_es_idempotente(): void
    {
        $this->seed(AgentePromptsSeeder::class);
        $this->seed(AgentePromptsSeeder::class);

        $this->assertSame(1, AgentePromptVigente::version());
        $this->assertDatabaseCount('agente_prompts', count(FaseConversacion::cases()));
    }
}
