<?php

namespace Tests\Feature;

use App\Services\Whatsapp\TwilioWhatsappClient;
use Tests\TestCase;
use Twilio\AuthStrategy\AuthStrategy;
use Twilio\Http\Client as TwilioHttpClient;
use Twilio\Http\Response;
use Twilio\Rest\Client as TwilioClient;

class TwilioWhatsappClientTest extends TestCase
{
    /**
     * Fake del transporte HTTP del SDK de Twilio (no de la red vía
     * Http::fake(), que no aplica acá — el SDK usa Guzzle directo, no el
     * Http:: de Laravel) — captura la última petición para poder
     * inspeccionarla, y devuelve una respuesta canónica de creación de
     * mensaje.
     */
    private function crearClienteConFake(): array
    {
        $fake = new class implements TwilioHttpClient
        {
            /** @var array<string, mixed>|null */
            public ?array $ultimaPeticion = null;

            public int $peticiones = 0;

            /** @var list<string> */
            public array $cuerpos = [];

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
            ): Response {
                $this->ultimaPeticion = compact('method', 'url', 'params', 'data', 'headers');
                $this->peticiones++;
                $this->cuerpos[] = (string) ($data['Body'] ?? '');

                return new Response(201, json_encode([
                    'sid' => 'SM_test_1234',
                    'account_sid' => 'AC_test',
                    'status' => 'queued',
                ]));
            }
        };

        $twilioClient = new TwilioClient('AC_test', 'token_test', null, null, $fake);

        return [$twilioClient, $fake];
    }

    public function test_enviar_texto_manda_from_to_y_body_correctos_y_devuelve_el_message_sid(): void
    {
        config(['services.twilio.whatsapp_from' => '+15557654321']);

        [$twilioClient, $fake] = $this->crearClienteConFake();

        $sid = (new TwilioWhatsappClient($twilioClient))->enviarTexto('+15551234567', 'Hola, gracias por tu mensaje.');

        $this->assertSame('SM_test_1234', $sid);
        $this->assertSame('whatsapp:+15557654321', $fake->ultimaPeticion['data']['From']);
        $this->assertSame('whatsapp:+15551234567', $fake->ultimaPeticion['data']['To']);
        $this->assertSame('Hola, gracias por tu mensaje.', $fake->ultimaPeticion['data']['Body']);
    }

    public function test_no_duplica_el_prefijo_whatsapp_si_ya_viene_incluido(): void
    {
        config(['services.twilio.whatsapp_from' => 'whatsapp:+15557654321']);

        [$twilioClient, $fake] = $this->crearClienteConFake();

        (new TwilioWhatsappClient($twilioClient))->enviarTexto('whatsapp:+15551234567', 'hola');

        $this->assertSame('whatsapp:+15557654321', $fake->ultimaPeticion['data']['From']);
        $this->assertSame('whatsapp:+15551234567', $fake->ultimaPeticion['data']['To']);
    }

    public function test_un_texto_de_mas_de_1500_caracteres_sale_en_varios_mensajes(): void
    {
        config(['services.twilio.whatsapp_from' => '+15557654321']);

        [$twilioClient, $fake] = $this->crearClienteConFake();

        $mensaje = implode("\n\n", array_map(fn (int $i) => "{$i}. ".str_repeat('pregunta ', 40), range(1, 10)));

        (new TwilioWhatsappClient($twilioClient))->enviarTexto('+15551234567', $mensaje);

        $this->assertGreaterThan(1, $fake->peticiones);

        foreach ($fake->cuerpos as $cuerpo) {
            $this->assertLessThanOrEqual(1600, mb_strlen($cuerpo), 'Twilio rechaza más de 1600 caracteres (error 21617).');
        }
    }
}
