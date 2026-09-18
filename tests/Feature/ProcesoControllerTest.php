<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcesoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cliente_no_puede_ver_el_diagrama_del_proceso(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->get(route('proceso.show'))->assertForbidden();
        $this->actingAs($cliente)->get(route('proceso.raw'))->assertForbidden();
    }

    public function test_un_preparador_y_un_administrador_pueden_ver_el_diagrama_del_proceso(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $administrador = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($preparador)->get(route('proceso.show'))->assertOk();
        $this->actingAs($administrador)->get(route('proceso.show'))->assertOk();
    }

    public function test_raw_devuelve_el_html_del_diagrama_generado_por_archify(): void
    {
        $administrador = User::factory()->create(['role' => UserRole::Administrator]);

        $response = $this->actingAs($administrador)->get(route('proceso.raw'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('<html', $response->getContent());
    }
}
