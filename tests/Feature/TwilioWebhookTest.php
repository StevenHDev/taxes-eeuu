<?php

namespace Tests\Feature;

use App\Enums\EstadoControlConversacion;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class TwilioWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.twilio.auth_token' => 'test-auth-token']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'From' => 'whatsapp:+15551234567',
            'To' => 'whatsapp:+15557654321',
            'Body' => 'Hola, necesito ayuda con mis impuestos',
            'NumMedia' => '0',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function firmar(string $url, array $data): string
    {
        return (new RequestValidator((string) config('services.twilio.auth_token')))
            ->computeSignature($url, $data);
    }

    public function test_una_firma_valida_guarda_el_mensaje_y_crea_el_control_en_modo_agente(): void
    {
        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');

        $this->withHeaders(['X-Twilio-Signature' => $this->firmar($url, $payload)])
            ->post($url, $payload)
            ->assertOk();

        $mensaje = WhatsappMensaje::query()->where('twilio_message_sid', $payload['MessageSid'])->first();

        $this->assertNotNull($mensaje);
        $this->assertSame('+15551234567', $mensaje->telefono);
        $this->assertSame(RolMensajeWhatsapp::Cliente, $mensaje->rol);
        $this->assertSame($payload['Body'], $mensaje->contenido);
        $this->assertNull($mensaje->cliente_id);

        $control = WhatsappControl::query()->where('telefono', '+15551234567')->first();
        $this->assertNotNull($control);
        $this->assertSame(EstadoControlConversacion::Agente, $control->estado);
    }

    public function test_una_firma_invalida_se_rechaza_y_no_guarda_nada(): void
    {
        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');

        $this->withHeaders(['X-Twilio-Signature' => 'firma-inventada'])
            ->post($url, $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('whatsapp_mensajes', 0);
        $this->assertDatabaseCount('whatsapp_control', 0);
    }

    public function test_sin_cabecera_de_firma_se_rechaza(): void
    {
        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');

        $this->post($url, $payload)->assertForbidden();
    }

    public function test_un_reintento_con_el_mismo_message_sid_no_duplica_el_mensaje(): void
    {
        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');
        $firma = $this->firmar($url, $payload);

        $this->withHeaders(['X-Twilio-Signature' => $firma])->post($url, $payload)->assertOk();
        $this->withHeaders(['X-Twilio-Signature' => $firma])->post($url, $payload)->assertOk();

        $this->assertSame(
            1,
            WhatsappMensaje::query()->where('twilio_message_sid', $payload['MessageSid'])->count(),
        );
    }

    public function test_vincula_el_cliente_existente_por_telefono(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15551234567']);

        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');

        $this->withHeaders(['X-Twilio-Signature' => $this->firmar($url, $payload)])
            ->post($url, $payload)
            ->assertOk();

        $mensaje = WhatsappMensaje::query()->where('twilio_message_sid', $payload['MessageSid'])->first();
        $control = WhatsappControl::query()->where('telefono', '+15551234567')->first();

        $this->assertSame($cliente->id, $mensaje->cliente_id);
        $this->assertSame($cliente->id, $control->cliente_id);
    }

    public function test_en_modo_humano_igual_guarda_el_mensaje_entrante(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        WhatsappControl::query()->create([
            'telefono' => '+15551234567',
            'estado' => EstadoControlConversacion::Humano,
            'tomado_por_user_id' => $preparador->id,
            'tomado_en' => now(),
        ]);

        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');

        $this->withHeaders(['X-Twilio-Signature' => $this->firmar($url, $payload)])
            ->post($url, $payload)
            ->assertOk();

        $this->assertDatabaseHas('whatsapp_mensajes', [
            'twilio_message_sid' => $payload['MessageSid'],
            'telefono' => '+15551234567',
        ]);
    }
}
