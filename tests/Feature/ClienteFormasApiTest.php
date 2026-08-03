<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\FormState;
use App\Enums\UserRole;
use App\Models\FormaCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClienteFormasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_declara_formas_aplicables_y_devuelve_el_checklist_pendiente(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        Sanctum::actingAs($preparador, [ApiAbility::ClientesWrite->value]);

        $this->postJson("/api/clientes/{$cliente->id}/formas", [
            'tax_year' => 2025,
            'formas' => ['schedule_c', 'schedule_e'],
        ])
            ->assertOk()
            ->assertJsonPath('tax_year', 2025)
            ->assertJsonPath('completo', false);

        $this->assertDatabaseHas('formas_cliente', [
            'user_id' => $cliente->id, 'forma' => 'schedule_c', 'tax_year' => 2025, 'estado' => 'en_progreso',
        ]);
        $this->assertDatabaseHas('formas_cliente', [
            'user_id' => $cliente->id, 'forma' => 'schedule_e', 'tax_year' => 2025, 'estado' => 'en_progreso',
        ]);
    }

    public function test_una_forma_invalida_devuelve_422(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesWrite->value]);

        $this->postJson("/api/clientes/{$cliente->id}/formas", [
            'tax_year' => 2025,
            'formas' => ['forma_que_no_existe'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['formas.0']);
    }

    public function test_falta_tax_year_devuelve_422(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesWrite->value]);

        $this->postJson("/api/clientes/{$cliente->id}/formas", ['formas' => ['form_1040']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_year']);
    }

    public function test_declarar_formas_de_nuevo_no_resetea_una_forma_ya_completa(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => FormState::Completo,
        ]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesWrite->value]);

        $this->postJson("/api/clientes/{$cliente->id}/formas", [
            'tax_year' => 2025,
            'formas' => ['form_1040'],
        ])->assertOk();

        $this->assertDatabaseHas('formas_cliente', [
            'user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'completo',
        ]);
    }

    public function test_requiere_ability_clientes_write(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $this->postJson("/api/clientes/{$cliente->id}/formas", [
            'tax_year' => 2025,
            'formas' => ['form_1040'],
        ])->assertForbidden();
    }

    public function test_un_preparador_no_puede_declarar_formas_de_un_cliente_que_no_tiene_asignado(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $otroPreparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $otroPreparador->id]);
        Sanctum::actingAs($preparador, [ApiAbility::ClientesWrite->value]);

        $this->postJson("/api/clientes/{$cliente->id}/formas", [
            'tax_year' => 2025,
            'formas' => ['form_1040'],
        ])->assertForbidden();
    }
}
