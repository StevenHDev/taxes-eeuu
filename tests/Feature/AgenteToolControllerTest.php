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
            'fase' => FaseConversacion::Recoleccion->value,
            'tool_name' => 'consultar_documentos_extra',
            'activo' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('agente_tool_estados', [
            'fase' => FaseConversacion::Recoleccion->value,
            'tool_name' => 'consultar_documentos_extra',
            'activo' => false,
        ]);
    }

    public function test_desactivar_en_recoleccion_aplica_tambien_a_cierre(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->patch(route('agente.tools.update'), [
            'fase' => FaseConversacion::Recoleccion->value,
            'tool_name' => 'guardar_campo_cliente',
            'activo' => false,
        ])->assertRedirect();

        $this->assertFalse(AgenteToolEstados::activo(FaseConversacion::Recoleccion, 'guardar_campo_cliente'));
        $this->assertFalse(AgenteToolEstados::activo(FaseConversacion::Cierre, 'guardar_campo_cliente'));
    }

    public function test_no_se_puede_desactivar_una_tool_que_no_existe_en_esa_fase(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->patch(route('agente.tools.update'), [
            'fase' => FaseConversacion::VerificacionCuenta->value,
            'tool_name' => 'guardar_campo_cliente',
            'activo' => false,
        ])->assertStatus(422);
    }

    public function test_un_preparador_no_puede_togglear(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->patch(route('agente.tools.update'), [
            'fase' => FaseConversacion::Recoleccion->value,
            'tool_name' => 'guardar_campo_cliente',
            'activo' => false,
        ])->assertForbidden();
    }

    public function test_reactivar_borra_el_efecto_de_una_desactivacion_previa(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        AgenteToolEstado::query()->create([
            'fase' => FaseConversacion::Recoleccion->value,
            'tool_name' => 'consultar_documentos_extra',
            'activo' => false,
        ]);
        AgenteToolEstados::invalidate();

        $this->actingAs($admin)->patch(route('agente.tools.update'), [
            'fase' => FaseConversacion::Recoleccion->value,
            'tool_name' => 'consultar_documentos_extra',
            'activo' => true,
        ])->assertRedirect();

        $this->assertTrue(AgenteToolEstados::activo(FaseConversacion::Recoleccion, 'consultar_documentos_extra'));
    }
}
