<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Models\AgentePrompt;
use App\Support\AgentePromptVigente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El borrador de prompts copia la versión vigente: el cambio de un archivo solo
 * entra si se pide la fase con --fases, y el comando avisa cuando no entró.
 */
class BorradorPromptsAgenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AgentePromptVigente::invalidate();

        foreach (FaseConversacion::cases() as $fase) {
            AgentePrompt::query()->create(['version' => 1, 'fase' => $fase->value, 'contenido' => 'vigente '.$fase->value, 'publicada_en' => now()->subDay()]);
        }
    }

    public function test_sin_fases_copia_la_vigente_y_avisa_de_las_fases_cuyo_archivo_es_distinto(): void
    {
        $this->artisan('agente:borrador-prompts')
            ->expectsOutputToContain('NO entró al borrador')
            ->assertSuccessful();

        $borrador = AgentePrompt::query()->where('version', 2)->where('fase', 'entrevista')->value('contenido');

        $this->assertSame('vigente entrevista', $borrador, 'Sin --fases el archivo no entra.');
    }

    public function test_con_fases_toma_el_archivo(): void
    {
        $this->artisan('agente:borrador-prompts', ['--fases' => 'entrevista'])->assertSuccessful();

        $borrador = (string) AgentePrompt::query()->where('version', 2)->where('fase', 'entrevista')->value('contenido');

        $this->assertSame(file_get_contents(base_path('prompt_actuales/fases/entrevista.md')), $borrador);
        $this->assertSame('vigente seguimiento', AgentePrompt::query()->where('version', 2)->where('fase', 'seguimiento')->value('contenido'));
    }
}
