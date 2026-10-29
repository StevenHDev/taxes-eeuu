<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\Controller;
use App\Models\CatalogoFiscal\CatalogoAnio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * /catalogo-fiscal → la pestaña Preguntas del año fiscal en curso (o del
 * último año cargado, si el actual todavía no tiene catálogo).
 */
class InicioController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        Gate::authorize('viewAny', CatalogoAnio::class);

        $actual = (int) config('tax.current_tax_year');
        $anio = CatalogoAnio::query()->where('tax_year', $actual)->exists()
            ? $actual
            : (CatalogoAnio::query()->max('tax_year') ?? $actual);

        return to_route('catalogo-fiscal.preguntas', ['anio' => $anio]);
    }
}
