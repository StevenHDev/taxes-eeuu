<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * Solo quien conoce el auth token de la cuenta de Twilio puede producir una
 * firma válida para esta URL exacta — reemplaza cualquier autenticación de
 * sesión/Sanctum en esta ruta pública. Detrás de un proxy (ver
 * bootstrap/app.php: trustProxies) `fullUrl()` ya refleja el esquema/host
 * originales, que es justo lo que Twilio firmó al hacer la petición.
 */
class VerifyTwilioSignature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $validator = new RequestValidator((string) config('services.twilio.auth_token'));

        $firmaValida = $validator->validate(
            $request->header('X-Twilio-Signature', ''),
            $request->fullUrl(),
            $request->post(),
        );

        abort_unless($firmaValida, 403, 'Firma de Twilio inválida.');

        return $next($request);
    }
}
