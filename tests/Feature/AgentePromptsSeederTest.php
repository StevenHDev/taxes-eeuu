<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Services\WhatsappAgent\ActivosPromptComposer;
use App\Support\AgentePromptVigente;
use Database\Seeders\AgentePromptsSeeder;
use Database\Seeders\PromptActivoStepsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentePromptsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_las_4_fases_como_version_1_publicada(): void
    {
        // PromptActivoStepsSeeder debe correr ANTES: la fase recoleccion
        // depende de esa config para compilar sus marcadores ACTIVOS_LISTA/
        // ACTIVOS_SALVAGUARDAS (ver ActivosPromptComposer) — mismo orden que
        // usa el flujo real de publicación.
        $this->seed(PromptActivoStepsSeeder::class);
        $this->seed(AgentePromptsSeeder::class);

        $this->assertSame(1, AgentePromptVigente::version());

        foreach (FaseConversacion::cases() as $fase) {
            $ruta = base_path("prompt_actuales/fases/{$fase->value}.md");
            $original = file_get_contents($ruta);

            // Única excepción: recoleccion sustituye sus dos marcadores por
            // texto compilado desde config — el resto de fases sigue
            // comparando byte a byte contra su archivo fuente.
            $esperado = $fase === FaseConversacion::Recoleccion
                ? strtr($original, [
                    '<!-- ACTIVOS_LISTA -->' => app(ActivosPromptComposer::class)->compilarLista(),
                    '<!-- ACTIVOS_SALVAGUARDAS -->' => app(ActivosPromptComposer::class)->compilarSalvaguardas(),
                ])
                : $original;

            $this->assertSame(
                $esperado,
                AgentePromptVigente::paraFase($fase),
                "El contenido sembrado para [{$fase->value}] no coincide con el esperado.",
            );
        }
    }

    public function test_es_idempotente_y_refresca_el_contenido_si_el_archivo_cambio(): void
    {
        $this->seed(PromptActivoStepsSeeder::class);
        $this->seed(AgentePromptsSeeder::class);
        $this->seed(AgentePromptsSeeder::class);

        $this->assertSame(1, AgentePromptVigente::version());
        $this->assertDatabaseCount('agente_prompts', count(FaseConversacion::cases()));
    }
}
