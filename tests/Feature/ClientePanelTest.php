<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\CampoReveal;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientePanelTest extends TestCase
{
    use RefreshDatabase;

    private function crearCampo(User $cliente, string $campo = 'ingresos', array $overrides = []): CampoCliente
    {
        return CampoCliente::query()->create(array_merge([
            'user_id' => $cliente->id,
            'forma' => 'form_1040',
            'campo' => $campo,
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => 1000,
            'estado' => 'recibido',
            'source' => 'agente_ia',
        ], $overrides));
    }

    public function test_un_cliente_no_puede_acceder_al_panel(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->get(route('clientes.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('clientes.show', $cliente))->assertForbidden();
    }

    public function test_un_preparador_solo_ve_a_sus_clientes_asignados(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $propio = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $ajeno = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($preparador)->get(route('clientes.show', $propio))->assertOk();
        $this->actingAs($preparador)->get(route('clientes.show', $ajeno))->assertForbidden();
    }

    public function test_un_administrador_ve_a_todos_los_clientes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)->get(route('clientes.show', $cliente))->assertOk();
    }

    public function test_un_preparador_puede_corregir_un_campo_manualmente_y_queda_en_el_historial(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->crearCampo($cliente);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'ingresos']).'?forma=form_1040', [
                'forma' => 'form_1040',
                'modo' => 'texto',
                'tipo_dato' => 'number',
                'contenido' => 9999,
            ])
            ->assertRedirect();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertSame(9999, $campo->valor);
        $this->assertSame('preparador', $campo->source->value);

        $historial = HistorialCambio::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertNotNull($historial);
        $this->assertSame('preparador', $historial->source->value);
    }

    public function test_un_preparador_no_puede_corregir_campos_de_un_cliente_ajeno(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $ajeno = User::factory()->create(['role' => UserRole::Client]);
        $this->crearCampo($ajeno);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $ajeno, 'campo' => 'ingresos']).'?forma=form_1040', [
                'forma' => 'form_1040',
                'modo' => 'texto',
                'tipo_dato' => 'number',
                'contenido' => 1,
            ])
            ->assertForbidden();
    }

    public function test_marcar_una_forma_como_revisada(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'estado' => 'en_progreso']);

        $this->actingAs($preparador)
            ->post(route('clientes.marcar-revisado', ['cliente' => $cliente, 'forma' => 'form_1040']))
            ->assertRedirect();

        $forma = FormaCliente::query()->where('user_id', $cliente->id)->first();
        $this->assertNotNull($forma->revisado_en);
        $this->assertSame($preparador->id, $forma->revisado_por);
    }

    public function test_revelar_un_campo_sensible_exige_reconfirmar_la_contrasena(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $campo = $this->crearCampo($cliente, 'identificacion_ssn_itin', ['valor_texto' => '123456789']);

        $this->actingAs($preparador)
            ->post(
                route('clientes.campos.reveal', ['cliente' => $cliente, 'campo' => 'identificacion_ssn_itin']).'?forma=form_1040',
                [],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(423);

        $this->assertSame(0, CampoReveal::query()->count());

        $this->actingAs($preparador)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clientes.campos.reveal', ['cliente' => $cliente, 'campo' => 'identificacion_ssn_itin']).'?forma=form_1040')
            ->assertOk()
            ->assertJson(['valor' => '123456789']);

        $this->assertSame(1, CampoReveal::query()->where('campo_cliente_id', $campo->id)->count());
    }
}
