<?php

namespace Tests\Feature;

use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Events\WhatsappMensajeRecibido;
use App\Models\User;
use App\Models\WhatsappMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WhatsappRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml fuerza BROADCAST_CONNECTION=null para el resto de la
        // suite (no tocar la red en tests que no son de esto). routes/channels.php
        // ya corrió una vez al bootear la app y registró los canales sobre esa
        // instancia "null" del broadcaster (BroadcastManager cachea una
        // instancia por driver) — cambiar el default acá alcanza un driver
        // "reverb" nuevo con $channels vacío, así que hay que volver a cargar
        // el archivo de rutas para que los registre también ahí (no dispara
        // conexión real, /broadcasting/auth solo corre el callback de
        // autorización en PHP).
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
    }

    public function test_guardar_un_mensaje_dispara_el_evento_de_broadcast(): void
    {
        Event::fake([WhatsappMensajeRecibido::class]);

        $mensaje = WhatsappMensaje::query()->create([
            'telefono' => '+15551234567',
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => 'hola',
            'mensaje_externo_id' => 'SM'.str_repeat('a', 32),
        ]);

        Event::assertDispatched(WhatsappMensajeRecibido::class, fn ($event) => $event->mensaje->is($mensaje));
    }

    public function test_un_administrador_puede_autorizar_el_canal_de_cualquier_telefono(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-whatsapp.15551234567',
                'socket_id' => '123.456',
            ])
            ->assertOk();
    }

    public function test_un_preparador_solo_autoriza_el_canal_de_su_propio_cliente(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'phone' => '+15551234567']);

        $this->actingAs($preparador)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-whatsapp.15551234567',
                'socket_id' => '123.456',
            ])
            ->assertOk();

        $this->actingAs($preparador)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-whatsapp.19999999999',
                'socket_id' => '123.456',
            ])
            ->assertForbidden();
    }
}
