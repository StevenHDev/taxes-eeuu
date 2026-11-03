<?php

namespace App\Services\Whatsapp;

use App\Support\TextoWhatsapp;
use Twilio\Rest\Client as TwilioClient;

/**
 * Envío de texto libre por WhatsApp (dentro de la ventana de 24h desde el
 * último mensaje del cliente — fuera de esa ventana, Twilio rechaza el envío
 * y hace falta una plantilla `ContentSid`, fuera de alcance de este esfuerzo
 * — ver docs/implementar_agente_n8n.md, "Alcance de este esfuerzo"). El
 * cliente `TwilioClient` se inyecta (ver AppServiceProvider) en vez de
 * construirse acá, para poder inyectar un `Twilio\Http\Client` de prueba sin
 * tocar la red real.
 */
class TwilioWhatsappClient
{
    public function __construct(private readonly TwilioClient $client) {}

    /**
     * Un texto de más de 1500 caracteres sale en varios mensajes seguidos
     * (Twilio rechaza más de 1600 y el cliente se quedaría sin respuesta).
     *
     * @return string el MessageSid del último mensaje enviado
     */
    public function enviarTexto(string $telefono, string $mensaje): string
    {
        $sid = '';

        foreach (TextoWhatsapp::partir($mensaje) as $parte) {
            $sid = $this->client->messages->create($this->comoWhatsapp($telefono), [
                'from' => $this->comoWhatsapp((string) config('services.twilio.whatsapp_from')),
                'body' => $parte,
            ])->sid;
        }

        return $sid;
    }

    private function comoWhatsapp(string $telefono): string
    {
        return str_starts_with($telefono, 'whatsapp:') ? $telefono : "whatsapp:{$telefono}";
    }
}
