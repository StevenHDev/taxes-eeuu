<?php

namespace Tests\Feature;

use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappConversacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_preparador_ve_la_conversacion_de_su_cliente(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create([
            'role' => UserRole::Client,
            'preparer_id' => $preparador->id,
            'phone' => '+13055550100',
        ]);

        WhatsappMensaje::query()->create([
            'telefono' => '+13055550100',
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => 'Hola buenas tardes',
            'mensaje_externo_id' => 'SM'.str_repeat('a', 32),
        ]);
        WhatsappMensaje::query()->create([
            'telefono' => '+13055550100',
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Agente,
            'contenido' => '¡Hola! ¿Ya tienes una cuenta creada en GlobalTax?',
            'mensaje_externo_id' => 'SM'.str_repeat('b', 32),
        ]);

        $this->actingAs($preparador)
            ->getJson(route('clientes.conversacion-whatsapp', $cliente))
            ->assertOk()
            ->assertJson([
                'mensajes' => [
                    ['role' => 'human', 'content' => 'Hola buenas tardes'],
                    ['role' => 'ai', 'content' => '¡Hola! ¿Ya tienes una cuenta creada en GlobalTax?'],
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
