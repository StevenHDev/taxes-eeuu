<?php

namespace Tests\Feature;

use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgenteMensajesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrador_ve_los_mensajes_de_cualquier_cliente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'name' => 'Jane Doe']);

        WhatsappMensaje::query()->create([
            'telefono' => '+15551234567',
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => 'Hola, necesito ayuda',
            'mensaje_externo_id' => 'SM'.str_repeat('a', 32),
            'proveedor' => 'twilio',
        ]);

        $response = $this->actingAs($admin)->get(route('agente.mensajes.index'))->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('agente/mensajes')
            ->has('mensajes', 1)
            ->where('mensajes.0.telefono', '+15551234567')
            ->where('mensajes.0.cliente_nombre', 'Jane Doe')
            ->where('mensajes.0.rol', 'cliente'));
    }

    public function test_un_preparador_no_puede_ver_la_bandeja_de_mensajes(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('agente.mensajes.index'))->assertForbidden();
    }

    public function test_un_cliente_no_puede_ver_la_bandeja_de_mensajes(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->get(route('agente.mensajes.index'))->assertForbidden();
    }

    public function test_solo_muestra_mensajes_de_los_ultimos_30_dias(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        // created_at no está en $fillable (nunca lo asigna el agente), así que
        // create() lo ignora y Eloquent lo pone en "ahora" — hay que forzarlo
        // en un segundo save(), que ya no vuelve a tocar created_at.
        $mensaje = WhatsappMensaje::query()->create([
            'telefono' => '+15551234567',
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => 'viejo',
            'mensaje_externo_id' => 'SM'.str_repeat('a', 32),
        ]);
        $mensaje->created_at = now()->subDays(31);
        $mensaje->save();

        $response = $this->actingAs($admin)->get(route('agente.mensajes.index'))->assertOk();

        $response->assertInertia(fn ($page) => $page->has('mensajes', 0));
    }
}
