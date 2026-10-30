<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usa en el servidor el mismo idioma que eligió la persona en el selector
 * (cookie `language`, que escribe hooks/use-language.ts). Así lo que arma el
 * servidor (textos de las preguntas, el consentimiento, avisos y errores)
 * sale en el mismo idioma que la interfaz. Sin cookie queda `APP_LOCALE`, y
 * el frontend lo toma del atributo `lang` de la página.
 */
class AplicarIdioma
{
    public const COOKIE = 'language';

    private const IDIOMAS = ['en', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        $idioma = $request->cookie(self::COOKIE);

        if (is_string($idioma) && in_array($idioma, self::IDIOMAS, true)) {
            App::setLocale($idioma);
        }

        return $next($request);
    }
}
