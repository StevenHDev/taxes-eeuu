<?php

namespace App\Services\Whatsapp;

use App\DataTransferObjects\MensajeEntranteWhatsapp;
use Illuminate\Http\Request;

/**
 * Un canal de WhatsApp — el resto del sistema (ProcesarMensajeWhatsappJob,
 * el agente conversacional) nunca conoce el proveedor concreto, solo habla
 * contra esta interfaz. Hoy la única implementación es TwilioChannel
 * (enlazada en AppServiceProvider); las pruebas la reemplazan por un fake.
 */
interface WhatsappChannel
{
    /**
     * Valida que la petición POST realmente venga del proveedor (firma HMAC
     * propia de cada uno) — se comprueba ANTES de tocar cualquier otra cosa
     * del request.
     */
    public function validarFirma(Request $request): bool;

    /**
     * Convierte el payload crudo del proveedor a un mensaje genérico. Null si el evento no es un mensaje
     * de usuario relevante (ej. un status update de entrega/lectura).
     */
    public function normalizarEntrante(Request $request): ?MensajeEntranteWhatsapp;

    /**
     * Envía texto libre al teléfono indicado (dentro de la ventana de 24h
     * del proveedor). Devuelve el id que el proveedor asignó al envío.
     */
    public function enviarTexto(string $telefono, string $mensaje): string;

    /**
     * Descarga una referencia de media (una de las de
     * MensajeEntranteWhatsapp::$mediaReferencias) a un archivo local.
     *
     * @return array{ruta_local: string, mime_type: string}
     */
    public function descargarMedia(string $referencia): array;
}
