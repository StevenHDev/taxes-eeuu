<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Models\AgentePrompt;
use App\Support\AgentePromptVigente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentePromptVigenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_hay_version_vigente_sin_ninguna_publicada(): void
    {
        AgentePrompt::query()->create([
            'version' => 1,
            'fase' => FaseConversacion::Recoleccion->value,
            'contenido' => 'borrador sin publicar',
            'publicada_en' => null,
        ]);

        $this->assertNull(AgentePromptVigente::version());
        $this->assertNull(AgentePromptVigente::paraFase(FaseConversacion::Recoleccion));
    }

    public function test_resuelve_el_contenido_por_fase_de_la_version_publicada_mas_reciente(): void
    {
        foreach (FaseConversacion::cases() as $fase) {
            AgentePrompt::query()->create([
                'version' => 1,
                'fase' => $fase->value,
                'contenido' => "v1 {$fase->value}",
                'publicada_en' => now()->subDay(),
            ]);
        }

        foreach (FaseConversacion::cases() as $fase) {
            AgentePrompt::query()->create([
                'version' => 2,
                'fase' => $fase->value,
                'contenido' => "v2 {$fase->value}",
                'publicada_en' => now()->subHour(),
            ]);
        }

        $this->assertSame(2, AgentePromptVigente::version());
        $this->assertSame(
            'v2 '.FaseConversacion::Recoleccion->value,
            AgentePromptVigente::paraFase(FaseConversacion::Recoleccion),
        );
    }

    public function test_ignora_una_version_publicada_a_futuro(): void
    {
        AgentePrompt::query()->create([
            'version' => 1,
            'fase' => FaseConversacion::Recoleccion->value,
            'contenido' => 'v1 vigente',
            'publicada_en' => now()->subHour(),
        ]);

        AgentePrompt::query()->create([
            'version' => 2,
            'fase' => FaseConversacion::Recoleccion->value,
            'contenido' => 'v2 todavia no',
            'publicada_en' => now()->addDay(),
        ]);

        $this->assertSame(1, AgentePromptVigente::version());
        $this->assertSame('v1 vigente', AgentePromptVigente::paraFase(FaseConversacion::Recoleccion));
    }

    public function test_invalidate_limpia_la_cache(): void
    {
        AgentePrompt::query()->create([
            'version' => 1,
            'fase' => FaseConversacion::Recoleccion->value,
            'contenido' => 'v1',
            'publicada_en' => now()->subHour(),
        ]);

        $this->assertSame(1, AgentePromptVigente::version());

        AgentePrompt::query()->create([
            'version' => 2,
            'fase' => FaseConversacion::Recoleccion->value,
            'contenido' => 'v2',
            'publicada_en' => now()->subMinute(),
        ]);

        // Sin invalidar, sigue leyendo la versión cacheada.
        $this->assertSame(1, AgentePromptVigente::version());

        AgentePromptVigente::invalidate();

        $this->assertSame(2, AgentePromptVigente::version());
    }
}
