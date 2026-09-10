<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Usuario de sistema que actúa como `actor` en EventoRecoleccionService
 * cuando el origen es el agente de WhatsApp — no hay token Sanctum ni sesión
 * HTTP de quien invoca (un job en cola), pero `campos_cliente.actualizado_por`
 * y `historial_cambios.modificado_por` son FKs reales a `users.id` que deben
 * quedar en algo legible para auditoría, igual que ya existe un usuario
 * "Agente conversacional" para el camino HTTP del agente externo.
 */
class AgenteWhatsappUser
{
    private const EMAIL = 'agente-whatsapp@system.local';

    public static function resolver(): User
    {
        return User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Agente de WhatsApp',
                'password' => Hash::make(Str::random(40)),
                'role' => UserRole::Administrator,
            ],
        );
    }
}
