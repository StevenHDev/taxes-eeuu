<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Descarga un media adjunto de un mensaje entrante de Twilio (exige Basic
 * Auth con las credenciales de la cuenta para acceder a `MediaUrlN` — a
 * diferencia de la URL del webhook en sí, que no la requiere).
 *
 * Solo descarga — no decide qué campo del catálogo corresponde a cada
 * documento (eso lo decide el modelo conversacional turno a turno, ver
 * prompt_actuales/fases/recoleccion.md) ni extrae texto (ver
 * DocumentoExtraccionService, invocado por quien orquesta la recepción del
 * media). Implementa la mitad "Twilio" de WhatsappChannel::descargarMedia().
 */
class TwilioMediaDownloader
{
    /**
     * @return array{ruta_local: string, mime_type: string}
     */
    public function descargar(string $url): array
    {
        $respuesta = Http::withBasicAuth(
            (string) config('services.twilio.account_sid'),
            (string) config('services.twilio.auth_token'),
        )->get($url);

        throw_unless($respuesta->successful(), new RuntimeException(
            "No se pudo descargar el media de Twilio ({$url}): HTTP {$respuesta->status()}",
        ));

        // El Content-Type real de la respuesta es más confiable que confiar
        // en que el llamador conozca de antemano el mime type — puede traer
        // un charset pegado (ej. "image/jpeg; charset=UTF-8"), se recorta.
        $mimeType = trim(explode(';', $respuesta->header('Content-Type') ?: 'application/octet-stream')[0]);

        $ruta = tempnam(sys_get_temp_dir(), 'twilio_media_').'.'.$this->extensionPara($mimeType);
        file_put_contents($ruta, $respuesta->body());

        return ['ruta_local' => $ruta, 'mime_type' => $mimeType];
    }

    /**
     * `test: true` evita el chequeo `is_uploaded_file()` de Symfony — el
     * archivo no llegó por un POST HTTP real, ya está en disco porque
     * `descargar()` lo puso ahí.
     */
    public function comoArchivoSubido(string $rutaLocal, string $mimeType, string $nombreOriginal): UploadedFile
    {
        return new UploadedFile($rutaLocal, $nombreOriginal, $mimeType, test: true);
    }

    private function extensionPara(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
