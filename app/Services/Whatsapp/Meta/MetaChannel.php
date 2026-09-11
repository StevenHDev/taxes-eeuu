<?php

namespace App\Services\Whatsapp\Meta;

use App\DataTransferObjects\MensajeEntranteWhatsapp;
use App\Services\Whatsapp\WhatsappChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Implementación de WhatsappChannel para Meta Cloud API (WhatsApp Business
 * Platform) — alternativa a Twilio, seleccionable con `WHATSAPP_PROVIDER=meta`
 * (ver WhatsappChannel y AppServiceProvider). Referencia:
 * https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class MetaChannel implements WhatsappChannel
{
    /**
     * Meta manda los parámetros de verificación como `hub.mode`/
     * `hub.verify_token`/`hub.challenge` (con puntos) — PHP convierte
     * automáticamente los puntos de una query string a guiones bajos al
     * poblar $_GET, así que Request::query() los ve como hub_mode/etc., no
     * como un error de este código.
     */
    public function manejarHandshake(Request $request): ?Response
    {
        $modoValido = $request->query('hub_mode') === 'subscribe';
        $tokenValido = hash_equals(
            (string) config('services.meta.verify_token'),
            (string) $request->query('hub_verify_token', ''),
        );

        if (! $modoValido || ! $tokenValido) {
            return response('Forbidden', 403);
        }

        return response((string) $request->query('hub_challenge', ''));
    }

    /**
     * Meta firma el body crudo completo con HMAC-SHA256 usando el App
     * Secret — a diferencia de Twilio, que firma la URL + parámetros.
     */
    public function validarFirma(Request $request): bool
    {
        $firma = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($firma, 'sha256=')) {
            return false;
        }

        $esperada = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) config('services.meta.app_secret'));

        return hash_equals($esperada, $firma);
    }

    public function normalizarEntrante(Request $request): ?MensajeEntranteWhatsapp
    {
        $mensaje = data_get($request->json()->all(), 'entry.0.changes.0.value.messages.0');

        // Meta manda el mismo webhook para eventos sin mensaje de usuario
        // (ej. confirmaciones de entrega/lectura) — no hay nada que procesar.
        if (! is_array($mensaje)) {
            return null;
        }

        $tipo = (string) ($mensaje['type'] ?? '');
        $texto = $tipo === 'text' ? (string) data_get($mensaje, 'text.body', '') : '';

        $referencias = collect(['image', 'document', 'audio', 'video'])
            ->map(fn (string $tipoMedia) => data_get($mensaje, "{$tipoMedia}.id"))
            ->filter()
            ->values()
            ->all();

        $telefono = (string) ($mensaje['from'] ?? '');

        return new MensajeEntranteWhatsapp(
            proveedor: 'meta',
            mensajeId: (string) ($mensaje['id'] ?? ''),
            telefono: $telefono === '' ? '' : '+'.ltrim($telefono, '+'),
            texto: $texto,
            mediaReferencias: $referencias,
        );
    }

    public function enviarTexto(string $telefono, string $mensaje): string
    {
        $respuesta = Http::withToken((string) config('services.meta.access_token'))
            ->post($this->urlEnviarMensaje(), [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($telefono, '+'),
                'type' => 'text',
                'text' => ['body' => $mensaje],
            ]);

        throw_unless($respuesta->successful(), new RuntimeException(
            "Fallo el envío por Meta: HTTP {$respuesta->status()} — {$respuesta->body()}",
        ));

        return (string) $respuesta->json('messages.0.id');
    }

    /**
     * @return array{ruta_local: string, mime_type: string}
     */
    public function descargarMedia(string $referencia): array
    {
        // Meta resuelve el media en dos pasos: primero el id da una URL
        // temporal (vence rápido) + mime_type, luego se descarga esa URL —
        // ambas peticiones necesitan el mismo Bearer token.
        $info = Http::withToken((string) config('services.meta.access_token'))->get($this->urlMedia($referencia));

        throw_unless($info->successful(), new RuntimeException(
            "No se pudo resolver el media de Meta ({$referencia}): HTTP {$info->status()}",
        ));

        $descarga = Http::withToken((string) config('services.meta.access_token'))->get((string) $info->json('url'));

        throw_unless($descarga->successful(), new RuntimeException(
            "No se pudo descargar el media de Meta ({$referencia}): HTTP {$descarga->status()}",
        ));

        $ruta = tempnam(sys_get_temp_dir(), 'meta_media_');
        file_put_contents($ruta, $descarga->body());

        return ['ruta_local' => $ruta, 'mime_type' => (string) $info->json('mime_type', 'application/octet-stream')];
    }

    private function urlEnviarMensaje(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.meta.api_version'),
            config('services.meta.phone_number_id'),
        );
    }

    private function urlMedia(string $mediaId): string
    {
        return sprintf('https://graph.facebook.com/%s/%s', config('services.meta.api_version'), $mediaId);
    }
}
