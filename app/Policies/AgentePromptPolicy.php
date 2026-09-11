<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * El prompt del agente conversacional (por fase, versionado) es de gestión
 * exclusiva de administradores — un preparador no decide cómo se comporta el
 * agente con NINGÚN cliente.
 */
class AgentePromptPolicy
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
