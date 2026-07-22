<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_un_administrador_accede_al_panel_de_usuarios(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk();
        $this->actingAs($preparador)->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_un_administrador_puede_crear_un_usuario_con_cualquier_rol(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Preparador',
            'email' => 'nuevo-preparador@example.com',
            'password' => 'password123',
            'role' => 'preparer',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'nuevo-preparador@example.com', 'role' => 'preparer']);
    }

    public function test_un_preparador_no_puede_crear_usuarios_desde_el_panel_de_administracion(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->post(route('usuarios.store'), [
            'name' => 'Intento',
            'email' => 'intento@example.com',
            'password' => 'password123',
            'role' => 'administrator',
        ])->assertForbidden();
    }

    public function test_un_administrador_puede_reasignar_el_preparador_de_un_cliente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparadorViejo = User::factory()->create(['role' => UserRole::Preparer]);
        $preparadorNuevo = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparadorViejo->id]);

        $this->actingAs($admin)->patch(route('usuarios.update', $cliente), [
            'name' => $cliente->name,
            'email' => $cliente->email,
            'role' => 'client',
            'preparer_id' => $preparadorNuevo->id,
        ])->assertRedirect();

        $this->assertSame($preparadorNuevo->id, $cliente->fresh()->preparer_id);
    }

    public function test_un_preparador_no_puede_editar_el_perfil_de_su_propio_cliente(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)->patch(route('usuarios.update', $cliente), [
            'name' => $cliente->name,
            'email' => $cliente->email,
            'role' => 'administrator',
        ])->assertForbidden();

        $this->assertSame(UserRole::Client, $cliente->fresh()->role);
    }

    public function test_un_administrador_no_puede_eliminarse_a_si_mismo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->delete(route('usuarios.destroy', $admin))->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_un_administrador_puede_eliminar_un_cliente_y_sus_datos_en_cascada(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'form_1040',
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => 1000,
            'estado' => 'recibido',
            'source' => 'agente_ia',
        ]);

        $this->actingAs($admin)->delete(route('usuarios.destroy', $cliente))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $cliente->id]);
        $this->assertDatabaseMissing('campos_cliente', ['user_id' => $cliente->id]);
    }
}
