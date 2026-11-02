<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F2.5: la entrevista del portal es solo de clientes; el personal vuelve a
 * su panel.
 */
class EnsureEntrevistaNueva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario?->role !== UserRole::Client) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
