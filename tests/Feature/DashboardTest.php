<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
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

    public function test_un_cliente_no_ve_resumen_de_estadisticas(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('resumen', null));
    }

    public function test_un_preparador_ve_el_resumen_solo_de_sus_clientes(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $propio = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $ajeno = User::factory()->create(['role' => UserRole::Client]);

        FormaCliente::query()->create(['user_id' => $propio->id, 'forma' => 'form_1040', 'estado' => 'completo']);
        FormaCliente::query()->create(['user_id' => $ajeno->id, 'forma' => 'form_1040', 'estado' => 'completo']);

        CampoCliente::query()->create([
            'user_id' => $propio->id, 'forma' => 'form_1040', 'campo' => 'ingresos',
            'tipo_campo' => 'dato', 'modo' => 'texto', 'valor_texto' => 1000,
            'estado' => 'recibido', 'source' => 'agente_ia',
        ]);
        HistorialCambio::query()->create([
            'user_id' => $propio->id, 'forma' => 'form_1040', 'campo' => 'ingresos',
            'valor_nuevo' => 1000, 'source' => 'agente_ia',
        ]);

        $this->actingAs($preparador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('resumen.total', 1)
                ->where('resumen.completo', 1)
                ->where('resumen.sin_iniciar', 0)
                ->where('resumen.formas_completas_porcentaje', 100)
                ->where('resumen.distribucion_por_forma.0.forma', 'form_1040')
                ->where('resumen.distribucion_por_forma.0.cantidad', 1)
                ->where('resumen.pendientes_revisar.0.cliente_nombre', $propio->name)
                ->where('resumen.actividad_reciente.0.cliente_nombre', $propio->name)
                ->where('resumen.ultimos_clientes.0.id', $propio->id)
                ->has('resumen.actividad_por_dia', 7));
    }
}
