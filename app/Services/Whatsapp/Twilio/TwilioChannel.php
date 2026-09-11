<?php

namespace App\Services\Whatsapp\Twilio;

use App\DataTransferObjects\MensajeEntranteWhatsapp;
use App\Services\Whatsapp\TwilioMediaDownloader;
use App\Services\Whatsapp\TwilioWhatsappClient;
use App\Services\Whatsapp\WhatsappChannel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * Implementación de WhatsappChannel para Twilio — envuelve TwilioWhatsappClient
 * (envío) y TwilioMediaDownloader (media), y mueve acá la validación de firma
 * que antes vivía en el middleware VerifyTwilioSignature (ahora innecesario:
 * el controlador del webhook es genérico y delega en el canal vigente).
 */
class TwilioChannel implements WhatsappChannel
{
    public function __construct(
        private readonly TwilioWhatsappClient $cliente,
        private readonly TwilioMediaDownloader $media,
    ) {}

    /**
     * Twilio no tiene handshake de verificación de webhook.
     */
    public function manejarHandshake(Request $request): ?Response
    {
        return null;
    }

    /**
     * Solo quien conoce el auth token de la cuenta puede producir una firma
     * válida para esta URL exacta. Detrás de un proxy (ver bootstrap/app.php:
     * trustProxies) `fullUrl()` ya refleja el esquema/host originales, que es
     * justo lo que Twilio firmó al hacer la petición.
     */
    public function validarFirma(Request $request): bool
    {
        $validador = new RequestValidator((string) config('services.twilio.auth_token'));

        return $validador->validate(
            $request->header('X-Twilio-Signature', ''),
            $request->fullUrl(),
            $request->post(),
        );
    }

    public function normalizarEntrante(Request $request): ?MensajeEntranteWhatsapp
    {
        $messageSid = (string) $request->input('MessageSid', '');

        if ($messageSid === '') {
            return null;
        }

        $from = (string) $request->input('From', '');
        $telefono = str_starts_with($from, 'whatsapp:') ? substr($from, strlen('whatsapp:')) : $from;

        $numMedia = (int) $request->input('NumMedia', 0);
        $referencias = [];

        for ($i = 0; $i < $numMedia; $i++) {
            $url = $request->input("MediaUrl{$i}");

            if (is_string($url) && $url !== '') {
                $referencias[] = $url;
            }
        }

        return new MensajeEntranteWhatsapp(
            proveedor: 'twilio',
            mensajeId: $messageSid,
            telefono: $telefono,
            texto: (string) $request->input('Body', ''),
            mediaReferencias: $referencias,
        );
    }

    public function enviarTexto(string $telefono, string $mensaje): string
    {
        return $this->cliente->enviarTexto($telefono, $mensaje);
    }

    public function descargarMedia(string $referencia): array
    {
        return $this->media->descargar($referencia);
    }
}
