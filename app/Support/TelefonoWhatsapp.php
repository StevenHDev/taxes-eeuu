<?php

namespace App\Support;

/**
 * Normaliza un teléfono de WhatsApp a un único formato canónico
 * ("+dígitos", siempre con "+", sin espacios/guiones/paréntesis ni el
 * prefijo "whatsapp:" de Twilio) — fuente única de verdad para los puntos
 * que antes tenían cada uno su propia normalización ad-hoc (User::phone(),
 * TwilioChannel::normalizarEntrante(), MetaChannel::normalizarEntrante()).
 *
 * Bug real en producción: TwilioChannel solo quitaba el prefijo
 * "whatsapp:" y confiaba en que Twilio siempre mandara el "+" en `From` —
 * cuando no fue así, el mismo cliente quedó con dos filas en
 * whatsapp_control ("573213445027" sin cliente_id, y "+573213445027" con
 * el cliente_id correcto), fragmentando también whatsapp_mensajes: un
 * where('telefono', ...) exacto solo encontraba la mitad de la
 * conversación. whatsapp_control/whatsapp_mensajes son columnas de string
 * planas sin cast — cualquier código nuevo que compare o guarde un
 * teléfono de WhatsApp debe pasar primero por acá.
 */
class TelefonoWhatsapp
{
    public static function normalizar(?string $telefono): ?string
    {
        if ($telefono === null || trim($telefono) === '') {
            return null;
        }

        $sinPrefijo = str_starts_with($telefono, 'whatsapp:') ? substr($telefono, strlen('whatsapp:')) : $telefono;

        $limpio = (string) preg_replace('/[^\d+]/', '', $sinPrefijo);

        if ($limpio === '') {
            return null;
        }

        return str_starts_with($limpio, '+') ? $limpio : '+'.$limpio;
    }
}
