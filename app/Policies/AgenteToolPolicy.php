<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Qué tools ve el agente por fase es de gestión exclusiva de
 * administradores — mismo criterio que AgentePromptPolicy: un preparador no
 * decide cómo se comporta el agente con NINGÚN cliente.
 */
class AgenteToolPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    public function update(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }
}
