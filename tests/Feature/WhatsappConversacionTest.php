<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappConversacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_preparador_ve_la_conversacion_normalizada_de_su_cliente(): void
    {
        // Formato real tal como lo guarda n8n: plano (sin wrapper "data"), y
        // los turnos del agente traen un rastro de herramientas antepuesto
        // ("[Used tools: ...]") que no debe llegar al chat.
        Http::fake([
            '*/rest/v1/globaltax_registro_whatsapp*' => Http::response([
                [
                    'session_id' => '+1 (305) 555-0100',
                    'created_at' => '2026-08-26T21:04:17.479465',
                    'message' => [
                        'type' => 'human',
                        'content' => 'Hola buenas tardes',
                        'additional_kwargs' => [],
                        'response_metadata' => [],
                    ],
                ],
                [
                    'session_id' => '+1 (305) 555-0100',
                    'created_at' => '2026-08-26T21:04:17.561102',
                    'message' => [
                        'type' => 'ai',
                        'content' => '[Used tools: Tool: Think1, Input: {}, Result: [{"response":"Pensando: iniciar conversación."}]] ¡Hola! Antes de empezar, ¿ya tienes una cuenta creada en GlobalTax?',
                        'tool_calls' => [],
                        'additional_kwargs' => [],
                        'response_metadata' => [],
                        'invalid_tool_calls' => [],
                    ],
                ],
            ], 200),
        ]);

        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create([
            'role' => UserRole::Client,
            'preparer_id' => $preparador->id,
            'phone' => '+1 (305) 555-0100',
        ]);

        $response = $this->actingAs($preparador)
            ->getJson(route('clientes.conversacion-whatsapp', $cliente));

        $response->assertOk()->assertJson([
            'mensajes' => [
                [
                    'role' => 'human',
                    'content' => 'Hola buenas tardes',
                    'created_at' => '2026-08-26T21:04:17.479465Z',
                ],
                [
                    'role' => 'ai',
                    'content' => '¡Hola! Antes de empezar, ¿ya tienes una cuenta creada en GlobalTax?',
                    'created_at' => '2026-08-26T21:04:17.561102Z',
                ],
            ],
        ]);

        Http::assertSent(fn ($request) => str_contains(urldecode((string) $request->url()), 'session_id=ilike.*13055550100*'));
    }

    public function test_marca_created_at_como_utc_sin_importar_el_formato_que_devuelva_supabase(): void
    {
        Http::fake([
            '*/rest/v1/globaltax_registro_whatsapp*' => Http::response([
                // Formato con espacio en vez de "T" (como se ve en el editor de Supabase).
                [
                    'session_id' => '+13055550100',
                    'created_at' => '2026-08-26 21:04:17.479465',
                    'message' => ['type' => 'human', 'content' => 'uno'],
                ],
                // Ya trae offset explícito: no debe tocarse.
                [
                    'session_id' => '+13055550100',
                    'created_at' => '2026-08-26T21:04:17-05:00',
                    'message' => ['type' => 'human', 'content' => 'dos'],
                ],
            ], 200),
        ]);

        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create([
            'role' => UserRole::Client,
            'preparer_id' => $preparador->id,
            'phone' => '+13055550100',
        ]);

        $this->actingAs($preparador)
            ->getJson(route('clientes.conversacion-whatsapp', $cliente))
            ->assertOk()
            ->assertJson([
                'mensajes' => [
                    ['content' => 'uno', 'created_at' => '2026-08-26T21:04:17.479465Z'],
                    ['content' => 'dos', 'created_at' => '2026-08-26T21:04:17-05:00'],
                ],
            ]);
    }

    public function test_un_preparador_no_puede_ver_la_conversacion_de_un_cliente_ajeno(): void
    {
        $otroPreparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create([
            'role' => UserRole::Client,
            'preparer_id' => $otroPreparador->id,
            'phone' => '+13055550100',
        ]);

        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)
            ->getJson(route('clientes.conversacion-whatsapp', $cliente))
            ->assertForbidden();
    }

    public function test_devuelve_lista_vacia_si_el_cliente_no_tiene_telefono(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create([
            'role' => UserRole::Client,
            'preparer_id' => $preparador->id,
            'phone' => null,
        ]);

        $this->actingAs($preparador)
            ->getJson(route('clientes.conversacion-whatsapp', $cliente))
            ->assertOk()
            ->assertJson(['mensajes' => []]);
    }
}
