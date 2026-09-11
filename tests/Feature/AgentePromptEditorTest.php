<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Enums\UserRole;
use App\Models\AgentePrompt;
use App\Models\User;
use App\Support\AgentePromptVigente;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentePromptEditorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function fasesPayload(string $sufijo = ''): array
    {
        return collect(FaseConversacion::cases())
            ->mapWithKeys(fn (FaseConversacion $f) => [$f->value => "contenido {$f->value}{$sufijo}"])
            ->all();
    }

    public function test_un_administrador_ve_el_contenido_vigente_cuando_no_hay_borrador(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->seed(AgentePromptsSeeder::class);

        $response = $this->actingAs($admin)->get(route('agente.prompts.index'))->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('agente/prompts')
            ->where('versionVigente', 1)
            ->where('versionBorrador', 2)
            ->where('hayBorrador', false)
            ->has('fases', count(FaseConversacion::cases())));
    }

    public function test_un_preparador_no_puede_ver_el_editor(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('agente.prompts.index'))->assertForbidden();
    }

    public function test_guardar_borrador_crea_la_version_siguiente_sin_publicarla(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->seed(AgentePromptsSeeder::class);

        $this->actingAs($admin)
            ->post(route('agente.prompts.borrador'), ['fases' => $this->fasesPayload(' editado')])
            ->assertRedirect();

        $this->assertSame(1, AgentePromptVigente::version());

        $filas = AgentePrompt::query()->where('version', 2)->get();
        $this->assertCount(count(FaseConversacion::cases()), $filas);
        $this->assertTrue($filas->every(fn (AgentePrompt $p) => $p->publicada_en === null));
        $this->assertSame(
            'contenido '.FaseConversacion::Recoleccion->value.' editado',
            $filas->firstWhere('fase', FaseConversacion::Recoleccion->value)->contenido,
        );
    }

    public function test_guardar_borrador_dos_veces_reutiliza_la_misma_version(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->seed(AgentePromptsSeeder::class);

        $this->actingAs($admin)->post(route('agente.prompts.borrador'), ['fases' => $this->fasesPayload(' v1')]);
        $this->actingAs($admin)->post(route('agente.prompts.borrador'), ['fases' => $this->fasesPayload(' v2')]);

        $this->assertSame(2, AgentePrompt::query()->max('version'));
        $this->assertDatabaseCount('agente_prompts', count(FaseConversacion::cases()) * 2);
    }

    public function test_publicar_marca_la_version_borrador_como_vigente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->seed(AgentePromptsSeeder::class);

        $this->actingAs($admin)->post(route('agente.prompts.borrador'), ['fases' => $this->fasesPayload(' nuevo')]);
        $this->actingAs($admin)->post(route('agente.prompts.publicar'))->assertRedirect();

        $this->assertSame(2, AgentePromptVigente::version());
        $this->assertSame(
            'contenido '.FaseConversacion::Recoleccion->value.' nuevo',
            AgentePromptVigente::paraFase(FaseConversacion::Recoleccion),
        );
    }

    public function test_publicar_sin_borrador_pendiente_falla(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->seed(AgentePromptsSeeder::class);

        $this->actingAs($admin)->post(route('agente.prompts.publicar'))->assertStatus(422);
    }

    public function test_un_preparador_no_puede_guardar_ni_publicar(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $this->seed(AgentePromptsSeeder::class);

        $this->actingAs($preparador)
            ->post(route('agente.prompts.borrador'), ['fases' => $this->fasesPayload()])
            ->assertForbidden();

        $this->actingAs($preparador)->post(route('agente.prompts.publicar'))->assertForbidden();
    }
}
