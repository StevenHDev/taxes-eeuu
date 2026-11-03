<?php

namespace App\Http\Controllers;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Lectura\EstadoExcepcion;
use App\Http\Requests\ResolverExcepcionRequest;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Lectura\ColaExcepciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La cola de excepciones del preparador (F4.7a). Las reglas de cada salida
 * viven en ColaExcepciones.
 */
class ExcepcionController extends Controller
{
    public function index(Request $request, ColaExcepciones $cola): Response
    {
        $this->authorize('viewAny', User::class);

        $filtros = $request->only(['severidad', 'estado', 'cliente']);
        $excepciones = $cola->lista($request->user(), $filtros);

        return Inertia::render('excepciones/index', [
            'excepciones' => $excepciones,
            'filtros' => [
                'severidad' => SeveridadRegla::tryFrom((string) ($filtros['severidad'] ?? ''))?->value,
                'estado' => EstadoExcepcion::tryFrom((string) ($filtros['estado'] ?? ''))?->value,
                'cliente' => ($filtros['cliente'] ?? null) === null ? null : (int) $filtros['cliente'],
            ],
            // Para el filtro por cliente: los que tienen algo abierto, sin filtrar.
            'clientes' => collect($cola->lista($request->user()))
                ->pluck('cliente')->unique('id')->sortBy('nombre')->values()->all(),
        ]);
    }

    public function aceptar(ResolverExcepcionRequest $request, Excepcion $excepcion, ColaExcepciones $cola): RedirectResponse
    {
        try {
            $cola->aceptar($excepcion, (string) $request->validated('nota'), $request->user());
        } catch (TransicionNoPermitida $e) {
            throw ValidationException::withMessages(['nota' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Aceptaste la excepción con tu nota.')]);

        return back();
    }

    public function consultar(ResolverExcepcionRequest $request, Excepcion $excepcion, ColaExcepciones $cola): RedirectResponse
    {
        try {
            $cola->consultar($excepcion, (string) $request->validated('texto'), $request->user());
        } catch (TransicionNoPermitida $e) {
            throw ValidationException::withMessages(['texto' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Le consultamos al cliente con tu pregunta.')]);

        return back();
    }
}
