<?php

namespace App\DataTransferObjects;

/**
 * Mensaje entrante de WhatsApp ya normalizado, sin importar de qué proveedor
 * vino (Twilio o Meta Cloud API) — ver App\Services\Whatsapp\WhatsappChannel.
 * Es lo que ProcesarMensajeWhatsappJob recibe en vez del payload crudo de un
 * proveedor en particular.
 */
final readonly class MensajeEntranteWhatsapp
{
    /**
     * @param  string  $proveedor  'twilio' | 'meta' — solo para trazabilidad
     *                             (columna `whatsapp_mensajes.proveedor`), nunca para
     *                             ramificar lógica de negocio fuera del propio canal.
     * @param  string  $mensajeId  id único del mensaje asignado por el proveedor — clave
     *                             de idempotencia (columna `mensaje_externo_id`).
     * @param  array<int, string>  $mediaReferencias  referencias a media en el formato propio
     *                                                de cada proveedor (URL para Twilio, media id para
     *                                                Meta) — se resuelven vía WhatsappChannel::descargarMedia().
     */
    public function __construct(
        public string $proveedor,
        public string $mensajeId,
        public string $telefono,
        public string $texto,
        public array $mediaReferencias = [],
    ) {}
}
