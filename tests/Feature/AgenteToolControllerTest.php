<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Enums\UserRole;
use App\Models\AgenteToolEstado;
use App\Models\User;
use App\Support\AgenteToolEstados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgenteToolControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrador_ve_el_catalogo_de_tools_por_fase(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $response = $this->actingAs($admin)->get(route('agente.tools.index'))->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('agente/tools')
            ->has('fases', count(FaseConversacion::cases()))
            ->where('fases.0.fase', FaseConversacion::VerificacionCuenta->value)
            ->has('fases.0.tools', 1) // crear_cliente_taxes (think queda excluida)
        );
    }

    public function test_un_preparador_no_puede_ver_el_catalogo(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('agente.tools.index'))->assertForbidden();
    }

    public function test_un_administrador_puede_desactivar_una_tool(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->patch(route('agente.tools.update'), [
            'fase' => FaseConversacion::Entrevista->value,
            'tool_name' => 'consultar_base_conocimiento',
            'activo' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('agente_tool_estados', [
            'fase' => FaseConversacion::Entrevista->value,
            'tool_name' => 'consultar_base_conocimiento',
            'activo' => false,
        ]);
    }

    public function test_no_se_puede_desactivar_una_tool_que_no_existe_en_esa_fase(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->patch(route('agente.tools.update'), [
            'fase' => FaseConversacion::VerificacionCuenta->value,
            'tool_name' => 'guardar_respuestas',
            'activo' => false,
        ])->assertStatus(422);
    }

    public function test_un_preparador_no_puede_togglear(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->patch(route('agente.tools.update'), [
            'fase' => FaseConversacion::Entrevista->value,
            'tool_name' => 'guardar_respuestas',
            'activo' => false,
        ])->assertForbidden();
    }

    public function test_reactivar_borra_el_efecto_de_una_desactivacion_previa(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        AgenteToolEstado::query()->create([
            'fase' => FaseConversacion::Entrevista->value,
            'tool_name' => 'consultar_base_conocimiento',
            'activo' => false,
        ]);
        AgenteToolEstados::invalidate();

        $this->actingAs($admin)->patch(route('agente.tools.update'), [
            'fase' => FaseConversacion::Entrevista->value,
            'tool_name' => 'consultar_base_conocimiento',
            'activo' => true,
        ])->assertRedirect();

        $this->assertTrue(AgenteToolEstados::activo(FaseConversacion::Entrevista, 'consultar_base_conocimiento'));
    }
}
