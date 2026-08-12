<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * La bitácora de actividad es de acceso exclusivo de administradores — un
 * preparador ve su propio trabajo dentro del panel de cada cliente, pero no
 * el registro general de qué hizo cada usuario en toda la plataforma.
 */
class BitacoraPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }
}
