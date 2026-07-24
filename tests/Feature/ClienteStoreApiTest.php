<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClienteStoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_cliente_con_nombre_email_y_telefono(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        Sanctum::actingAs($preparador, [ApiAbility::ClientesWrite->value]);

        $this->postJson('/api/clientes', [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'phone' => '+15551112222',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Juan Perez')
            ->assertJsonPath('email', 'juan@example.com')
            ->assertJsonPath('phone', '+15551112222');

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'phone' => '+15551112222',
            'role' => UserRole::Client->value,
            'preparer_id' => $preparador->id,
        ]);
    }

    public function test_un_admin_puede_asignar_el_preparador(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesWrite->value]);

        $this->postJson('/api/clientes', [
            'name' => 'Ana Lopez',
            'email' => 'ana@example.com',
            'preparer_id' => $preparador->id,
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'ana@example.com',
            'preparer_id' => $preparador->id,
        ]);
    }

    public function test_422_si_falta_el_nombre_o_el_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesWrite->value]);

        $this->postJson('/api/clientes', ['phone' => '+15550000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_422_si_el_email_ya_existe(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        User::factory()->create(['role' => UserRole::Client, 'email' => 'dup@example.com']);
        Sanctum::actingAs($admin, [ApiAbility::ClientesWrite->value]);

        $this->postJson('/api/clientes', ['name' => 'Dup', 'email' => 'dup@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_requiere_ability_clientes_write(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $this->postJson('/api/clientes', ['name' => 'Sin permiso', 'email' => 'no@example.com'])
            ->assertForbidden();
    }

    public function test_un_cliente_no_puede_crear_clientes(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($cliente, [ApiAbility::ClientesWrite->value]);

        $this->postJson('/api/clientes', ['name' => 'X', 'email' => 'x@example.com'])
            ->assertForbidden();
    }
}
