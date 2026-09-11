<?php

namespace App\Services\Whatsapp;

use App\Enums\MetodoExtraccionDocumento;
use App\Services\DocumentoExtraccion\DocumentoExtraccionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Descarga cada media adjunto de un mensaje entrante de WhatsApp (Twilio
 * exige Basic Auth con las credenciales de la cuenta para acceder a
 * `MediaUrlN` — a diferencia de la URL del webhook en sí, que no la
 * requiere) y lo entrega a DocumentoExtraccionService.
 *
 * No decide QUÉ campo del catálogo corresponde a cada documento — eso lo
 * decide el modelo conversacional turno a turno (ver
 * prompt_actuales/fases/recoleccion.md, RECEPCIÓN DE DOCUMENTOS). El texto
 * extraído de cada uno se agrega al mensaje que ve el modelo; si más tarde
 * decide invocar guardar_campo_cliente con modo="archivo" para uno de ellos,
 * `comoArchivoSubido()` envuelve el archivo ya descargado como un
 * `UploadedFile` real, listo para pasarle a `ToolExecutor::ejecutar()`.
 */
class TwilioMediaDownloader
{
    public function __construct(private readonly DocumentoExtraccionService $extraccion) {}

    /**
     * @param  array<string, mixed>  $payload  el payload crudo del webhook de Twilio (ver ProcesarMensajeWhatsappJob)
     * @return array<int, array{ruta_local: string, mime_type: string, nombre_original: string, texto: string, metodo: MetodoExtraccionDocumento}>
     */
    public function descargarYExtraer(array $payload): array
    {
        $numMedia = (int) ($payload['NumMedia'] ?? 0);
        $resultados = [];

        for ($i = 0; $i < $numMedia; $i++) {
            $url = $payload["MediaUrl{$i}"] ?? null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            $mimeType = (string) ($payload["MediaContentType{$i}"] ?? 'application/octet-stream');
            $rutaLocal = $this->descargar($url, $mimeType);
            $extraido = $this->extraccion->extraer($rutaLocal, $mimeType);

            $resultados[] = [
                'ruta_local' => $rutaLocal,
                'mime_type' => $mimeType,
                'nombre_original' => basename($rutaLocal),
                'texto' => $extraido['texto'],
                'metodo' => $extraido['metodo'],
            ];
        }

        return $resultados;
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

    private function descargar(string $url, string $mimeType): string
    {
        $respuesta = Http::withBasicAuth(
            (string) config('services.twilio.account_sid'),
            (string) config('services.twilio.auth_token'),
        )->get($url);

        throw_unless($respuesta->successful(), new RuntimeException(
            "No se pudo descargar el media de Twilio ({$url}): HTTP {$respuesta->status()}",
        ));

        $ruta = tempnam(sys_get_temp_dir(), 'twilio_media_').'.'.$this->extensionPara($mimeType);
        file_put_contents($ruta, $respuesta->body());

        return $ruta;
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
