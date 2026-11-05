<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_el_panel_llega_diferido_y_sin_el_resumen_viejo(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->missing('resumen')
                ->has('taxYearActual')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('panel.por_estado', 12)
                    ->has('panel.colas')
                    ->has('panel.avisos')
                    ->has('panel.ultimos_clientes')));
    }

    public function test_el_inicio_del_cliente_es_su_entrevista(): void
    {
        // F2.11: "Mi información" se retiró; la reemplaza la fase 5.
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->get(route('dashboard'))->assertRedirect(route('portal.entrevista.inicio'));
    }
}
