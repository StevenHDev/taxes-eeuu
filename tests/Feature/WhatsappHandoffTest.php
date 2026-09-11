<?php

namespace Tests\Feature;

use App\Enums\EstadoControlConversacion;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Twilio\AuthStrategy\AuthStrategy;
use Twilio\Http\Client as TwilioHttpClient;
use Twilio\Http\Response as TwilioResponse;
use Twilio\Rest\Client as TwilioClient;

class WhatsappHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.twilio.whatsapp_from' => '+15557654321']);

        $fake = new class implements TwilioHttpClient
        {
            public function request(
                string $method,
                string $url,
                array $params = [],
                array $data = [],
                array $headers = [],
                ?string $user = null,
                ?string $password = null,
                ?int $timeout = null,
                ?AuthStrategy $authStrategy = null,
            ): TwilioResponse {
                return new TwilioResponse(201, json_encode(['sid' => 'SM_manual_test', 'status' => 'queued']));
            }
        };

        $this->app->instance(TwilioClient::class, new TwilioClient('AC_test', 'token_test', null, null, $fake));
    }

    public function test_un_preparador_puede_tomar_el_control_de_la_conversacion(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'phone' => '+15551234567']);

        $this->actingAs($preparador)
            ->postJson(route('clientes.whatsapp.tomar-control', $cliente))
            ->assertOk()
            ->assertJsonPath('control.estado', 'humano');

        $control = WhatsappControl::query()->where('telefono', '+15551234567')->first();
        $this->assertSame(EstadoControlConversacion::Humano, $control->estado);
        $this->assertSame($preparador->id, $control->tomado_por_user_id);
        $this->assertNotNull($control->tomado_en);
    }

    public function test_un_preparador_puede_devolver_el_control(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'phone' => '+15551234567']);
        WhatsappControl::query()->create([
            'telefono' => '+15551234567',
            'estado' => EstadoControlConversacion::Humano,
            'tomado_por_user_id' => $preparador->id,
            'tomado_en' => now(),
        ]);

        $this->actingAs($preparador)
            ->postJson(route('clientes.whatsapp.devolver-control', $cliente))
            ->assertOk()
            ->assertJsonPath('control.estado', 'agente');

        $control = WhatsappControl::query()->where('telefono', '+15551234567')->first();
        $this->assertSame(EstadoControlConversacion::Agente, $control->estado);
        $this->assertNull($control->tomado_por_user_id);
    }

    public function test_un_preparador_ajeno_no_puede_tomar_control_de_un_cliente_que_no_es_suyo(): void
    {
        $otroPreparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $otroPreparador->id, 'phone' => '+15551234567']);

        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)
            ->postJson(route('clientes.whatsapp.tomar-control', $cliente))
            ->assertForbidden();
    }

    public function test_enviar_un_mensaje_manual_requiere_estar_en_modo_humano(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'phone' => '+15551234567']);

        $this->actingAs($preparador)
            ->postJson(route('clientes.whatsapp.enviar', $cliente), ['mensaje' => 'hola'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('whatsapp_mensajes', ['telefono' => '+15551234567']);
    }

    public function test_enviar_un_mensaje_manual_en_modo_humano_lo_envia_y_lo_guarda_como_preparador(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'phone' => '+15551234567']);
        WhatsappControl::query()->create([
            'telefono' => '+15551234567',
            'estado' => EstadoControlConversacion::Humano,
            'tomado_por_user_id' => $preparador->id,
            'tomado_en' => now(),
        ]);

        $this->actingAs($preparador)
            ->postJson(route('clientes.whatsapp.enviar', $cliente), ['mensaje' => 'Hola, soy tu preparador, ¿en qué te ayudo?'])
            ->assertOk()
            ->assertJsonPath('mensaje.role', 'preparador');

        $mensaje = WhatsappMensaje::query()->where('telefono', '+15551234567')->first();
        $this->assertSame(RolMensajeWhatsapp::Preparador, $mensaje->rol);
        $this->assertSame('Hola, soy tu preparador, ¿en qué te ayudo?', $mensaje->contenido);
        $this->assertSame('SM_manual_test', $mensaje->twilio_message_sid);
        $this->assertSame($cliente->id, $mensaje->cliente_id);
    }

    public function test_la_conversacion_mezcla_supabase_y_local_ordenada_por_instante_real(): void
    {
        Http::fake([
            '*/rest/v1/globaltax_registro_whatsapp*' => Http::response([
                [
                    'session_id' => '+15551234567',
                    'created_at' => '2026-08-26T21:00:00.000000',
                    'message' => ['type' => 'human', 'content' => 'mensaje viejo de supabase'],
                ],
            ], 200),
        ]);

        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'phone' => '+15551234567']);

        WhatsappMensaje::query()->create([
            'telefono' => '+15551234567',
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Agente,
            'contenido' => 'mensaje nuevo local',
            'twilio_message_sid' => 'SM'.str_repeat('a', 32),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($preparador)
            ->getJson(route('clientes.conversacion-whatsapp', $cliente))
            ->assertOk();

        $contenidos = collect($response->json('mensajes'))->pluck('content')->all();

        $this->assertSame(['mensaje viejo de supabase', 'mensaje nuevo local'], $contenidos);
        $this->assertSame('agente', $response->json('control.estado'));
    }

    public function test_no_se_puede_enviar_un_mensaje_manual_a_un_cliente_ajeno(): void
    {
        $otroPreparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $otroPreparador->id, 'phone' => '+15551234567']);

        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)
            ->postJson(route('clientes.whatsapp.enviar', $cliente), ['mensaje' => 'hola'])
            ->assertForbidden();
    }
}
