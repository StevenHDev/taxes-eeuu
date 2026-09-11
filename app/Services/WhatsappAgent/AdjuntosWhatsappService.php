<?php

namespace App\Services\WhatsappAgent;

use App\DataTransferObjects\AdjuntoWhatsapp;
use App\Services\DocumentoExtraccion\DocumentoExtraccionService;
use App\Services\Whatsapp\WhatsappChannel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resuelve las referencias de media de un mensaje entrante (agnóstico de
 * proveedor — Twilio o Meta, según el WhatsappChannel vigente) a texto
 * legible por el modelo: descarga cada una y la pasa por
 * DocumentoExtraccionService. Punto de integración que cierra Fase 4 (ver
 * docs/implementar_agente_n8n.md, "Punto de integración pendiente").
 */
class AdjuntosWhatsappService
{
    public function __construct(
        private readonly WhatsappChannel $canal,
        private readonly DocumentoExtraccionService $extraccion,
    ) {}

    /**
     * Nunca lanza: una falla al descargar/extraer UN adjunto (proveedor
     * caído, media ya expirado, etc.) no debe tumbar el turno entero — mismo
     * principio que WhatsappMensajeObserver con el broadcast. El cliente
     * sigue recibiendo respuesta; ese adjunto puntual simplemente no queda
     * anotado como archivo_url/texto_extraido, y el agente puede pedirlo de
     * nuevo con normalidad.
     *
     * @param  array<int, string>  $referencias
     * @return array<int, AdjuntoWhatsapp>
     */
    public function resolver(array $referencias): array
    {
        return collect($referencias)
            ->map(function (string $referencia) {
                try {
                    $descargado = $this->canal->descargarMedia($referencia);
                    $extraido = $this->extraccion->extraer($descargado['ruta_local'], $descargado['mime_type']);

                    return new AdjuntoWhatsapp(
                        referencia: $referencia,
                        rutaLocal: $descargado['ruta_local'],
                        mimeType: $descargado['mime_type'],
                        texto: $extraido['texto'],
                        metodo: $extraido['metodo'],
                    );
                } catch (Throwable $e) {
                    Log::warning('AdjuntosWhatsappService: no se pudo resolver un adjunto.', [
                        'referencia' => $referencia,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }
}
