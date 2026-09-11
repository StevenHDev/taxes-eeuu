<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Un preparador solo puede escuchar la conversación de un teléfono si es el
 * suyo (mismo criterio que ClientePolicy::tieneAcceso) — un administrador
 * escucha cualquiera. El "+" del teléfono se recorta al armar el nombre del
 * canal (ver WhatsappMensajeRecibido::broadcastOn()), así que se reconstruye
 * acá para la consulta.
 */
Broadcast::channel('whatsapp.{telefono}', function (User $user, string $telefono) {
    if ($user->role === UserRole::Administrator) {
        return true;
    }

    if ($user->role !== UserRole::Preparer) {
        return false;
    }

    return User::query()
        ->where('preparer_id', $user->id)
        ->where('phone', '+'.$telefono)
        ->exists();
});
