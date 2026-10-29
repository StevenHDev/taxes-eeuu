<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Catálogo fiscal del año (F1.4 y F1.5): lo ven preparadores y
 * administradores, nunca clientes; lo edita y lo valida contra el IRS solo
 * el administrador (decisión de Steven, 29 oct.). Más adelante también los
 * preparadores podrán validar (F2): es cambiar `validar()`.
 */
class CatalogoFiscalPolicy
{
    public function viewAny(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Preparer, UserRole::Administrator], true);
    }

    public function update(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    public function validar(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }
}
