<?php

namespace App\Services\Whatsapp;

use App\DataTransferObjects\MensajeEntranteWhatsapp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un canal de WhatsApp (Twilio o Meta Cloud API) — el resto del sistema
 * (ProcesarMensajeWhatsappJob, el agente conversacional) nunca conoce el
 * proveedor concreto, solo habla contra esta interfaz. Qué implementación
 * se resuelve la decide `config('services.whatsapp.provider')`, enlazado en
 * AppServiceProvider — cambiar esa variable de entorno es "el switch" entre
 * proveedores (ver docs/implementar_agente_n8n.md).
 */
interface WhatsappChannel
{
    /**
     * Handshake de verificación del webhook, si el proveedor lo exige (Meta
     * responde el `hub.challenge` a un GET si el verify_token calza; Twilio
     * no tiene este concepto). Null si este proveedor no maneja GET — el
     * controlador responde 404 en ese caso.
     */
    public function manejarHandshake(Request $request): ?Response;

    /**
     * Valida que la petición POST realmente venga del proveedor (firma HMAC
     * propia de cada uno) — se comprueba ANTES de tocar cualquier otra cosa
     * del request.
     */
    public function validarFirma(Request $request): bool;

    /**
     * Convierte el payload crudo (form-encoded para Twilio, JSON anidado
     * para Meta) a un mensaje genérico. Null si el evento no es un mensaje
     * de usuario relevante (ej. un status update de entrega/lectura).
     */
    public function normalizarEntrante(Request $request): ?MensajeEntranteWhatsapp;

    /**
     * Envía texto libre al teléfono indicado (dentro de la ventana de 24h
     * de cada proveedor). Devuelve el id que el proveedor asignó al envío.
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
