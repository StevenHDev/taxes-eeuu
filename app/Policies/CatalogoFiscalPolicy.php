<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Catálogo fiscal del año (F1.4, F1.5 y F2.1): lo ven preparadores y
 * administradores, nunca clientes; lo edita solo el administrador; lo
 * validan contra el IRS el administrador y el preparador, que es quien
 * acompaña el proceso del cliente (decisión de Steven, 29 oct.).
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
        return in_array($actor->role, [UserRole::Preparer, UserRole::Administrator], true);
    }
}
