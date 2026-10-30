<?php

namespace App\Services\Consentimiento;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Enlace para que un cliente nuevo cree su contraseña desde WhatsApp
 * (F2.4, decisión de Steven del 30 oct.). Lo arma siempre el código, nunca
 * el modelo.
 *
 * Es una URL firmada que vence a los 60 minutos y solo sirve mientras la
 * cuenta no tenga contraseña (`contrasena_definida_en` nula): al crearla,
 * todos los enlaces mandados dejan de servir. No usa el token de
 * restablecimiento a propósito: crear uno nuevo invalidaría el del correo
 * de bienvenida, que queda de respaldo. Una vez definida la contraseña, el
 * agente no manda enlaces: cambiarla va por "¿Olvidaste tu contraseña?" y
 * el correo, así que quien tome el WhatsApp del cliente no puede cambiarla.
 */
class EnlaceActivacion
{
    public const MINUTOS = 60;

    /**
     * El enlace, o null si la cuenta ya tiene contraseña o no es de un
     * cliente.
     */
    public function para(User $cliente): ?string
    {
        if (! $this->pendiente($cliente)) {
            return null;
        }

        return URL::temporarySignedRoute('activacion.show', now()->addMinutes(self::MINUTOS), ['usuario' => $cliente->id]);
    }

    public function pendiente(User $cliente): bool
    {
        return $cliente->contrasena_definida_en === null && $cliente->usaEntrevistaCatalogo();
    }

    /**
     * Prefijo común de todos los enlaces de activación, para saber si el
     * agente ya mandó uno en la conversación.
     */
    public static function prefijo(): string
    {
        return url('/activar');
    }
}
