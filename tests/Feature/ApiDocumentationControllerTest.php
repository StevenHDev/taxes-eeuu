<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cliente_no_puede_ver_la_documentacion_de_la_api(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->get(route('api-docs.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('api-docs.openapi'))->assertForbidden();
    }

    public function test_un_preparador_y_un_administrador_pueden_ver_la_documentacion_de_la_api(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $administrador = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($preparador)->get(route('api-docs.index'))->assertOk();
        $this->actingAs($administrador)->get(route('api-docs.index'))->assertOk();
    }
}
