<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F2.5: la entrevista del portal es solo para clientes con la bandera de la
 * entrevista nueva (F2.2). El resto sigue en el formulario actual.
 */
class EnsureEntrevistaNueva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // El portal es solo de clientes: el personal vuelve a su panel en
        // lugar de terminar en el 403 del formulario actual.
        if ($usuario?->role !== UserRole::Client) {
            return redirect()->route('dashboard');
        }

        if (! $usuario->usaEntrevistaCatalogo()) {
            return redirect()->route('portal.formulario');
        }

        return $next($request);
    }
}
