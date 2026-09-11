<?php

namespace Tests\Feature;

use App\Enums\EstadoControlConversacion;
use App\Enums\RolMensajeWhatsapp;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.provider' => 'meta',
            'services.meta.app_secret' => 'test-app-secret',
            'services.meta.verify_token' => 'test-verify-token',
            'services.meta.access_token' => 'test-access-token',
            'services.meta.phone_number_id' => '1234567890',
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'test-model',
        ]);

        $this->seed(AgentePromptsSeeder::class);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Gracias, en un momento seguimos.']]],
            ], 200),
            'graph.facebook.com/*/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.RESPUESTA']],
            ], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mensajeTexto(array $overrides = []): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [array_merge([
                            'id' => 'wamid.TEST123',
                            'from' => '15551234567',
                            'type' => 'text',
                            'text' => ['body' => 'Hola, necesito ayuda con mis impuestos'],
                        ], $overrides)],
                    ],
                ]],
            ]],
        ];
    }

    private function firmar(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');
    }

    public function test_el_handshake_de_verificacion_responde_el_challenge_si_el_token_calza(): void
    {
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=test-verify-token&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345', false);
    }

    public function test_el_handshake_se_rechaza_si_el_token_no_calza(): void
    {
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=incorrecto&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_una_firma_valida_guarda_el_mensaje_y_responde_por_meta(): void
    {
        $payload = $this->mensajeTexto();
        $body = json_encode($payload);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => $this->firmar($body),
        ], $body)->assertOk();

        $mensaje = WhatsappMensaje::query()->where('mensaje_externo_id', 'wamid.TEST123')->first();

        $this->assertNotNull($mensaje);
        $this->assertSame('+15551234567', $mensaje->telefono);
        $this->assertSame(RolMensajeWhatsapp::Cliente, $mensaje->rol);
        $this->assertSame('meta', $mensaje->proveedor);

        $control = WhatsappControl::query()->where('telefono', '+15551234567')->first();
        $this->assertSame(EstadoControlConversacion::Agente, $control->estado);

        $respuesta = WhatsappMensaje::query()->where('rol', RolMensajeWhatsapp::Agente)->first();
        $this->assertSame('wamid.RESPUESTA', $respuesta->mensaje_externo_id);
        $this->assertSame('meta', $respuesta->proveedor);
    }

    public function test_una_firma_invalida_se_rechaza_y_no_guarda_nada(): void
    {
        $payload = $this->mensajeTexto();
        $body = json_encode($payload);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => 'sha256=firma-invalida',
        ], $body)->assertForbidden();

        $this->assertDatabaseCount('whatsapp_mensajes', 0);
    }

    public function test_un_evento_sin_mensajes_no_produce_ningun_registro(): void
    {
        // Ej. un status update de entrega/lectura — mismo shape del webhook,
        // sin la clave `messages`.
        $payload = ['entry' => [['changes' => [['value' => ['statuses' => [['status' => 'delivered']]]]]]]];
        $body = json_encode($payload);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => $this->firmar($body),
        ], $body)->assertOk();

        $this->assertDatabaseCount('whatsapp_mensajes', 0);
    }
}
