<?php

namespace Tests\Feature;

use App\Enums\RolMensajeWhatsapp;
use App\Models\WhatsappMensaje;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use RuntimeException;
use Tests\TestCase;

/**
 * Incidente real en producción (2026-09-11): un certificado SSL inválido en
 * Reverb hizo que WhatsappMensajeObserver::created() tronara al guardar el
 * mensaje entrante del cliente — el job entero fallaba antes de que el
 * agente corriera, y el reintento automático se topaba con el chequeo de
 * idempotencia sin generar ninguna respuesta (ver ProcesarMensajeWhatsappJob).
 * Este test fija esa regresión: un broadcaster que falla nunca debe impedir
 * que el mensaje quede guardado.
 */
class WhatsappMensajeObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_falla_del_broadcaster_no_impide_guardar_el_mensaje(): void
    {
        Broadcast::extend('fallando_a_proposito', fn () => new class implements Broadcaster
        {
            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = [])
            {
                throw new RuntimeException('cURL error 60: SSL certificate problem (simulado)');
            }
        });

        config(['broadcasting.default' => 'fallando_a_proposito']);

        $mensaje = WhatsappMensaje::query()->create([
            'telefono' => '+15551234567',
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => 'Hola',
        ]);

        $this->assertDatabaseHas('whatsapp_mensajes', ['id' => $mensaje->id, 'contenido' => 'Hola']);
    }
}
