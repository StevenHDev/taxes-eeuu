<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * La bandeja de mensajes de WhatsApp (todo lo recibido/enviado por el
 * agente, de cualquier cliente) es de acceso exclusivo de administradores —
 * sirve para validar el comportamiento del agente y como log del webhook,
 * no es parte del trabajo de caso de un preparador (ver
 * ClienteController::conversacionWhatsapp para la conversación de UN
 * cliente puntual, esa sí visible para su preparador asignado).
 */
class WhatsappMensajePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }
}
