<?php

namespace App\Support;

/**
 * Oculta en un texto de WhatsApp los datos sensibles que el cliente escriba
 * en el chat (F2.8, T9): números de 9 dígitos con o sin guiones o espacios
 * (SSN, ITIN, número de ruta) y números largos de 12 a 17 dígitos
 * (cuentas). Se aplica antes de guardar el mensaje y, por lo tanto, antes de
 * mandarlo a OpenAI. El agente responde que eso va en el portal.
 *
 * A propósito no toca números con "+" adelante (teléfonos) ni montos con
 * punto o coma decimal.
 */
class RedactorDatosSensibles
{
    public const MARCA = '[dato sensible oculto]';

    public static function redactar(string $texto): string
    {
        // SSN o ITIN: 123-45-6789, 123 45 6789 o 123456789.
        $texto = (string) preg_replace('/(?<![\d+])\d{3}[-\s]?\d{2}[-\s]?\d{4}(?!\d)/', self::MARCA, $texto);

        // Cuentas bancarias largas.
        return (string) preg_replace('/(?<![\d+.,])\d{12,17}(?![\d.,])/', self::MARCA, $texto);
    }

    public static function tieneMarca(string $texto): bool
    {
        return str_contains($texto, self::MARCA);
    }
}
