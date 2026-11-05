<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expediente\CreditosDebidaDiligenciaRequest;
use App\Models\User;
use App\Services\Expediente\DebidaDiligencia;
use App\Services\Expediente\EstadosExpediente;
use App\Services\Expediente\FichaCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pestaña "Debida diligencia" de la ficha (F5.4): el respaldo del Form 8867 y
 * los créditos que el preparador confirma.
 */
class DebidaDiligenciaController extends Controller
{
    public function __construct(
        private readonly DebidaDiligencia $debida,
        private readonly FichaCliente $ficha,
        private readonly EstadosExpediente $estados,
    ) {}

    public function show(Request $request, User $cliente): Response
    {
        $this->authorize('view', $cliente);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        return Inertia::render('ficha/debida-diligencia', [
            'ficha' => $this->ficha->encabezado($cliente, $taxYear, $request->user()),
            'debida' => Inertia::defer(fn () => $this->debida->vista($cliente, $taxYear, app()->getLocale() === 'en'), rescue: true),
        ]);
    }

    public function creditos(CreditosDebidaDiligenciaRequest $request, User $cliente): RedirectResponse
    {
        $taxYear = (int) ($request->validated('tax_year') ?? config('tax.current_tax_year'));

        $expediente = $this->estados->recalcular($cliente, $taxYear);
        $this->debida->guardarCreditos($expediente, $request->creditos(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guardaste los créditos que se reclaman.')]);

        return back();
    }
}
