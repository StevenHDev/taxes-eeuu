<?php

namespace Tests\Feature;

use App\Enums\EstadoControlConversacion;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Twilio\AuthStrategy\AuthStrategy;
use Twilio\Http\Client as TwilioHttpClient;
use Twilio\Http\Response as TwilioResponse;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Security\RequestValidator;

class TwilioWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.twilio.auth_token' => 'test-auth-token',
            'services.twilio.whatsapp_from' => '+15557654321',
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'test-model',
        ]);

        $this->seed(AgentePromptsSeeder::class);
        $this->fakeTwilioSend();
    }

    /**
     * El job invoca a AgenteConversacionalService, que le pega a OpenAI —
     * fake genérico de "respuesta final sin tool calls" para no acoplar
     * estos tests (sobre el webhook/idempotencia/control) al contenido real
     * de la conversación. Cada test la invoca explícitamente (no vive en
     * setUp): Http::fake() resuelve por orden de REGISTRO, no el último
     * llamado — si esto quedara en setUp(), un test que necesite su propia
     * secuencia de respuestas (ver test_un_mensaje_con_media_...) nunca
     * podría reemplazarla, porque la de acá siempre matchearía primero.
     */
    private function fakeAgenteConversacional(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Gracias, en un momento seguimos.']]],
            ], 200),
        ]);
    }

    /**
     * El SDK de Twilio usa Guzzle directo, no Http:: de Laravel (ver
     * TwilioWhatsappClientTest) — se reemplaza el TwilioClient del
     * contenedor por uno con un transporte fake.
     */
    private function fakeTwilioSend(): void
    {
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
                return new TwilioResponse(201, json_encode(['sid' => 'SM_respuesta_test', 'status' => 'queued']));
            }
        };

        $this->app->instance(TwilioClient::class, new TwilioClient('AC_test', 'token_test', null, null, $fake));
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
        $this->fakeAgenteConversacional();
        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');

        $this->withHeaders(['X-Twilio-Signature' => $this->firmar($url, $payload)])
            ->post($url, $payload)
            ->assertOk();

        $mensaje = WhatsappMensaje::query()->where('mensaje_externo_id', $payload['MessageSid'])->first();

        $this->assertNotNull($mensaje);
        $this->assertSame('+15551234567', $mensaje->telefono);
        $this->assertSame(RolMensajeWhatsapp::Cliente, $mensaje->rol);
        $this->assertSame($payload['Body'], $mensaje->contenido);
        $this->assertNull($mensaje->cliente_id);

        $control = WhatsappControl::query()->where('telefono', '+15551234567')->first();
        $this->assertNotNull($control);
        $this->assertSame(EstadoControlConversacion::Agente, $control->estado);

        $respuesta = WhatsappMensaje::query()->where('telefono', '+15551234567')->where('rol', RolMensajeWhatsapp::Agente)->first();
        $this->assertNotNull($respuesta);
        $this->assertSame('Gracias, en un momento seguimos.', $respuesta->contenido);
        $this->assertSame('SM_respuesta_test', $respuesta->mensaje_externo_id);
        $this->assertSame(1, $respuesta->prompt_version);
    }

    /**
     * Bug real reportado en producción: Twilio no siempre garantiza el "+"
     * en `From` (o el valor llegó contaminado por otra vía) — sin
     * normalización, ese mensaje creaba una SEGUNDA fila de whatsapp_control
     * ("573213445027", cliente_id null) distinta de la que ya existía para
     * el mismo cliente ("+573213445027"), y whatsapp_mensajes quedaba
     * fragmentado entre los dos formatos. Ver App\Support\TelefonoWhatsapp.
     */
    public function test_un_from_sin_signo_mas_se_normaliza_y_calza_con_el_cliente_existente(): void
    {
        $this->fakeAgenteConversacional();
        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15551234567']);

        $payload = $this->payload(['From' => 'whatsapp:15551234567']);
        $url = route('api.whatsapp.webhook');

        $this->withHeaders(['X-Twilio-Signature' => $this->firmar($url, $payload)])
            ->post($url, $payload)
            ->assertOk();

        $mensaje = WhatsappMensaje::query()->where('mensaje_externo_id', $payload['MessageSid'])->first();
        $this->assertSame('+15551234567', $mensaje->telefono);
        $this->assertSame($cliente->id, $mensaje->cliente_id);

        $this->assertSame(1, WhatsappControl::query()->where('telefono', '+15551234567')->count());
        $this->assertSame(0, WhatsappControl::query()->where('telefono', '15551234567')->count());

        $control = WhatsappControl::query()->where('telefono', '+15551234567')->first();
        $this->assertSame($cliente->id, $control->cliente_id);
    }

    public function test_una_firma_invalida_se_rechaza_y_no_guarda_nada(): void
    {
        $this->fakeAgenteConversacional();
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
        $this->fakeAgenteConversacional();
        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');

        $this->post($url, $payload)->assertForbidden();
    }

    public function test_un_reintento_con_el_mismo_message_sid_no_duplica_el_mensaje(): void
    {
        $this->fakeAgenteConversacional();
        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');
        $firma = $this->firmar($url, $payload);

        $this->withHeaders(['X-Twilio-Signature' => $firma])->post($url, $payload)->assertOk();
        $this->withHeaders(['X-Twilio-Signature' => $firma])->post($url, $payload)->assertOk();

        $this->assertSame(
            1,
            WhatsappMensaje::query()->where('mensaje_externo_id', $payload['MessageSid'])->count(),
        );
    }

    public function test_vincula_el_cliente_existente_por_telefono(): void
    {
        $this->fakeAgenteConversacional();
        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15551234567']);

        $payload = $this->payload();
        $url = route('api.whatsapp.webhook');

        $this->withHeaders(['X-Twilio-Signature' => $this->firmar($url, $payload)])
            ->post($url, $payload)
            ->assertOk();

        $mensaje = WhatsappMensaje::query()->where('mensaje_externo_id', $payload['MessageSid'])->first();
        $control = WhatsappControl::query()->where('telefono', '+15551234567')->first();

        $this->assertSame($cliente->id, $mensaje->cliente_id);
        $this->assertSame($cliente->id, $control->cliente_id);
    }

    /**
     * Mismo generador de PDF mínimo válido que PdfTextExtractorServiceTest.
     */
    private function pdfConTexto(string $texto): string
    {
        $streamContenido = "BT /F1 12 Tf 20 700 Td ({$texto}) Tj ET";

        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Length '.strlen($streamContenido).' >>'."\nstream\n{$streamContenido}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objetos as $numero => $cuerpo) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$cuerpo}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    public function test_un_mensaje_con_media_descarga_extrae_y_guarda_el_documento(): void
    {
        Storage::fake('s3');

        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15551234567']);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $mediaUrl = 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages/MM_test/Media/ME_test';

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [['message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            ['id' => 'call_1', 'type' => 'function', 'function' => [
                                'name' => 'guardar_campo_cliente',
                                'arguments' => json_encode([
                                    'forma' => 'form_1040',
                                    'campo' => 'w2',
                                    'tipo_campo' => 'documento',
                                    'modo' => 'archivo',
                                    'contenido' => $mediaUrl,
                                ]),
                            ]],
                        ],
                    ]]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'Recibí tu W-2, gracias.']]],
                ]),
            $mediaUrl => Http::response($this->pdfConTexto('W-2 de prueba enviado por WhatsApp.'), 200, ['Content-Type' => 'application/pdf']),
        ]);

        $payload = $this->payload(['NumMedia' => '1', 'MediaUrl0' => $mediaUrl, 'Body' => '']);
        $url = route('api.whatsapp.webhook');

        $this->withHeaders(['X-Twilio-Signature' => $this->firmar($url, $payload)])
            ->post($url, $payload)
            ->assertOk();

        $documento = Documento::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first();
        $this->assertNotNull($documento);
        $this->assertSame('texto_pdf', $documento->metodo_extraccion->value);

        $mensaje = WhatsappMensaje::query()->where('mensaje_externo_id', $payload['MessageSid'])->first();
        $this->assertStringContainsString("archivo_url: {$mediaUrl}", $mensaje->contenido);
        $this->assertStringContainsString('W-2 de prueba enviado por WhatsApp.', $mensaje->contenido);
    }

    public function test_en_modo_humano_igual_guarda_el_mensaje_entrante(): void
    {
        // Necesario para que Http::assertNothingSent() de abajo sea una
        // aserción válida (exige que Http::fake() haya estado activo) — en
        // modo humano el job nunca llega a invocar al agente, así que este
        // fake nunca se consume, pero igual debe registrarse.
        $this->fakeAgenteConversacional();
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
            'mensaje_externo_id' => $payload['MessageSid'],
            'telefono' => '+15551234567',
        ]);

        // En modo humano el job nunca invoca al agente ni envía nada — solo
        // el mensaje entrante queda guardado.
        $this->assertDatabaseCount('whatsapp_mensajes', 1);
        Http::assertNothingSent();
    }
}
