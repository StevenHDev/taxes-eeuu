<?php

namespace App\Listeners;

use App\Enums\AccionAuditoria;
use App\Models\BitacoraActividad;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class RegistrarCierreSesion
{
    public function handle(Logout $event): void
    {
        /** @var User|null $usuario */
        $usuario = $event->user;

        // Logout::$user puede venir null (ej. sesión ya expirada/inválida) —
        // sin usuario no hay nada que auditar.
        if (! $usuario) {
            return;
        }

        // Nunca debe impedir el logout real — ver AuditoriaObserver::registrar().
        try {
            BitacoraActividad::query()->create([
                'actor_id' => $usuario->id,
                'actor_nombre' => $usuario->name,
                'actor_email' => $usuario->email,
                'accion' => AccionAuditoria::CierreSesion,
                'auditable_type' => User::class,
                'auditable_id' => $usuario->id,
                'etiqueta' => "{$usuario->name} ({$usuario->role->label()})",
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar el cierre de sesión en la bitácora de actividad.', [
                'user_id' => $usuario->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
