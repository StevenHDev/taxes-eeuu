<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sección del panel que muestra el diagrama del proceso completo de Global
 * Tax (docs/proceso-global-tax.html, generado con el skill archify — ver
 * agentes-subagentes-flujos-motor-decision.md) — pensado para que un
 * preparador/admin pueda explicarle el flujo a alguien sin describirlo en
 * texto.
 */
class ProcesoController extends Controller
{
    /**
     * El diagrama es un documento HTML autocontenido con su propio head,
     * CSS y JS (temas, pan/zoom, exportación) — se embebe en un <iframe>
     * apuntando a raw() en vez de inyectarlo como fragmento dentro de esta
     * página React, que rompería esas dos piezas (dos <html>/<head>, los
     * <script> de un dangerouslySetInnerHTML nunca se ejecutan).
     */
    public function show(): Response
    {
        $this->authorizeAcceso();

        return Inertia::render('proceso/show');
    }

    public function raw(): HttpResponse
    {
        $this->authorizeAcceso();

        return response(File::get(base_path('docs/proceso-global-tax.html')))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Sin Policy propia (no hay un modelo Eloquent detrás de esta página):
     * mismo criterio que oculta "Clientes" en el sidebar para un cliente
     * (ver AppSidebar::tieneAccesoAlPanel) — un cliente no debería ver el
     * proceso interno completo, solo su propio caso.
     */
    private function authorizeAcceso(): void
    {
        abort_if(request()->user()->role === UserRole::Client, 403);
    }
}
