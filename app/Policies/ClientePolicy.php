<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Autoriza el acceso al panel interno de clientes (sección 6.2 de la especificación):
 * un cliente jamás accede a este panel; un preparador solo ve sus clientes asignados;
 * un administrador ve y gestiona todo.
 */
class ClientePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role !== UserRole::Client;
    }

    public function view(User $actor, User $cliente): bool
    {
        return $this->tieneAcceso($actor, $cliente);
    }

    public function update(User $actor, User $cliente): bool
    {
        return $this->tieneAcceso($actor, $cliente);
    }

    private function tieneAcceso(User $actor, User $cliente): bool
    {
        return match ($actor->role) {
            UserRole::Administrator => true,
            UserRole::Preparer => $cliente->preparer_id === $actor->id,
            UserRole::Client => false,
        };
    }
}
