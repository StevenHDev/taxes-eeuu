<?php

namespace App\Http\Middleware;

use App\Services\Consentimiento\Consentimientos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F2.4: un cliente no entra a la entrevista ni a sus documentos sin haber
 * firmado el consentimiento §7216 vigente del año; lo manda a firmarlo. El
 * chat de dudas y la configuración de la cuenta no lo exigen.
 */
class EnsureConsentimiento7216
{
    public function __construct(private readonly Consentimientos $consentimientos) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario?->esCliente() && ! $this->consentimientos->firmado($usuario, (int) config('tax.current_tax_year'))) {
            return redirect()->route('portal.consentimiento');
        }

        return $next($request);
    }
}
