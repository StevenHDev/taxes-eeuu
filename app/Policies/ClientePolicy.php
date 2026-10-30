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

    /**
     * Alta rápida de un cliente desde /clientes — a diferencia de `create()`,
     * nunca permite elegir un rol distinto de `client`.
     */
    public function createCliente(User $actor): bool
    {
        return $actor->role !== UserRole::Client;
    }

    /**
     * Gestión completa de usuarios (cualquier rol) — exclusiva de administradores.
     * Deliberadamente separada de `update()`: un preparador tiene `update` sobre
     * sus clientes para corregir campos, pero eso NUNCA debe alcanzar para
     * cambiarles el rol o reasignarlos a otro preparador.
     */
    public function create(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    public function updateProfile(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    /**
     * "Eliminar" archiva (SoftDeletes): los datos se conservan 3 años.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Administrator && $actor->id !== $target->id;
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    /**
     * Solo los usuarios de prueba se eliminan definitivamente (F0.3).
     */
    public function forceDelete(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Administrator && $actor->id !== $target->id && $target->es_prueba;
    }

    public function marcarPrueba(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Administrator && $actor->id !== $target->id;
    }

    /**
     * La bandera de la entrevista nueva (F2.2) es solo de clientes y la
     * cambia solo el administrador.
     */
    public function marcarEntrevistaCatalogo(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Administrator && $target->role === UserRole::Client;
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
