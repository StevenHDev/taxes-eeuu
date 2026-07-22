<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * El catálogo de campos (qué pide cada formulario) es de administración
 * exclusiva de administradores — un preparador puede corregir/agregar/eliminar
 * VALORES de un cliente (ver ClientePolicy), pero no qué campos existen.
 */
class CatalogoPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    public function create(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    public function update(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    public function delete(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }
}
