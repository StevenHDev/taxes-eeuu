<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\Expediente\PanelExpedientes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly PanelExpedientes $panel) {}

    public function index(): Response|RedirectResponse
    {
        $user = request()->user();

        // El inicio del cliente es su entrevista (F2.5).
        if ($user->role === UserRole::Client) {
            return redirect()->route('portal.entrevista.inicio');
        }

        $taxYear = (int) request()->query('tax_year', config('tax.current_tax_year'));

        return Inertia::render('dashboard', [
            'panel' => Inertia::defer(fn () => $this->panel->resumen($user, $taxYear), rescue: true),
            'taxYearActual' => $taxYear,
        ]);
    }
}
