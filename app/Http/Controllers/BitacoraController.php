<?php

namespace App\Http\Controllers;

use App\Models\BitacoraActividad;
use Inertia\Inertia;
use Inertia\Response;

class BitacoraController extends Controller
{
    /**
     * Ventana de 500 filas/30 días: la tabla `bitacora_actividad` crece con
     * cada guardado de campo, y esta página (como `clientes/index`,
     * `usuarios/index`) es una DataTable 100% client-side — sin este límite,
     * enviar "todo lo que ha pasado siempre" se vuelve impracticable. Si el
     * volumen lo amerita más adelante, la solución de fondo es paginación de
     * servidor, fuera de este alcance.
     */
    private const LIMITE_FILAS = 500;

    public function index(): Response
    {
        $this->authorize('viewAny', BitacoraActividad::class);

        $eventos = BitacoraActividad::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('created_at')
            ->limit(self::LIMITE_FILAS)
            ->get()
            ->map(fn (BitacoraActividad $evento) => [
                'id' => $evento->id,
                'created_at' => $evento->created_at,
                'actor_nombre' => $evento->actor_nombre,
                'actor_email' => $evento->actor_email,
                'accion' => $evento->accion,
                'auditable_type' => $evento->auditable_type ? class_basename($evento->auditable_type) : null,
                'etiqueta' => $evento->etiqueta,
                'campos_afectados' => $evento->campos_afectados,
                'ip_address' => $evento->ip_address,
            ]);

        return Inertia::render('bitacora/index', [
            'eventos' => $eventos,
        ]);
    }
}
