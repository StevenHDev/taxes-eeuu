<?php

namespace App\Listeners;

use App\Enums\AccionAuditoria;
use App\Models\BitacoraActividad;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

/**
 * Fortify usa el SessionGuard estándar de Laravel, así que este evento ya se
 * dispara sin tocar Fortify ni crear un controlador de login propio — ver
 * AppServiceProvider::boot().
 */
class RegistrarInicioSesion
{
    public function handle(Login $event): void
    {
        /** @var User $usuario */
        $usuario = $event->user;

        // Nunca debe impedir el login real — ver AuditoriaObserver::registrar().
        try {
            BitacoraActividad::query()->create([
                'actor_id' => $usuario->id,
                'actor_nombre' => $usuario->name,
                'actor_email' => $usuario->email,
                'accion' => AccionAuditoria::InicioSesion,
                'auditable_type' => User::class,
                'auditable_id' => $usuario->id,
                'etiqueta' => "{$usuario->name} ({$usuario->role->label()})",
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar el inicio de sesión en la bitácora de actividad.', [
                'user_id' => $usuario->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
