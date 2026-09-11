<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Qué sabe el agente por fuera de los datos del cliente es de gestión
 * exclusiva de administradores — mismo criterio que AgentePromptPolicy y
 * AgenteToolPolicy: un preparador no decide qué conocimiento general usa el
 * agente con NINGÚN cliente.
 */
class BaseConocimientoPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    public function create(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    public function delete(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }
}
