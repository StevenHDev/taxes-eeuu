<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClienteBuscarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_encuentra_un_cliente_por_id(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'phone' => '+15551112222']);

        Sanctum::actingAs($preparador, [ApiAbility::ClientesRead->value]);

        $this->getJson('/api/clientes/buscar?id='.$cliente->id)
            ->assertOk()
            ->assertJsonPath('id', $cliente->id)
            ->assertJsonPath('phone', '+15551112222');
    }

    public function test_encuentra_un_cliente_por_telefono(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'phone' => '+15551112222']);

        Sanctum::actingAs($preparador, [ApiAbility::ClientesRead->value]);

        $this->getJson('/api/clientes/buscar?phone=%2B15551112222')
            ->assertOk()
            ->assertJsonPath('id', $cliente->id);
    }

    public function test_404_si_no_encuentra_el_telefono(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $this->getJson('/api/clientes/buscar?phone=%2B10000000000')
            ->assertNotFound();
    }

    public function test_422_si_no_manda_ni_id_ni_telefono(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $this->getJson('/api/clientes/buscar')->assertUnprocessable();
    }

    public function test_un_preparador_no_encuentra_el_telefono_de_un_cliente_ajeno(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        User::factory()->create(['role' => UserRole::Client, 'phone' => '+15550000000']);

        Sanctum::actingAs($preparador, [ApiAbility::ClientesRead->value]);

        $this->getJson('/api/clientes/buscar?phone=%2B15550000000')
            ->assertNotFound();
    }

    public function test_requiere_ability_clientes_read(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::EventosWrite->value]);

        $this->getJson('/api/clientes/buscar?id='.$admin->id)->assertForbidden();
    }
}
