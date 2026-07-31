<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DeterminacionFiscalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeterminacionFiscalController extends Controller
{
    public function __construct(private readonly DeterminacionFiscalService $determinaciones) {}

    public function store(Request $request, User $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        // Acción mutante: tax_year requerido en el body, sin default — mismo
        // criterio que marcar-revisado.
        $request->validate(['tax_year' => ['required', 'integer', 'digits:4']]);

        $this->determinaciones->calcularPara($cliente, $request->integer('tax_year'));

        return back();
    }
}
