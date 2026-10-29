<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F0.1 del documento madre (mínimo T1): el personal (roles de
 * `security.mfa_roles`) solo usa la plataforma con la verificación en dos
 * pasos confirmada o una passkey. Sin ninguna de las dos se lo manda a
 * Ajustes → Seguridad, donde un aviso le explica qué hacer.
 *
 * Quedan libres las rutas que necesita para configurarlo: perfil y
 * seguridad, confirmar la contraseña, el 2FA y las passkeys de Fortify,
 * verificar el correo y cerrar sesión.
 */
class EnsureStaffHasMfa
{
    /**
     * @var list<string>
     */
    private const RUTAS_LIBRES = [
        'profile.*',
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.*',
        'passkey.*',
        'verification.*',
        'well-known.passkeys',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->debeConfigurarMfa() || $request->routeIs(self::RUTAS_LIBRES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Activa la verificación en dos pasos o una passkey para continuar.');
        }

        return redirect()->route('security.edit');
    }
}
