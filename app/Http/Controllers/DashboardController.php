<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ManagesClientes;

    public function __construct(private readonly DashboardSummaryService $summary) {}

    public function index(): Response
    {
        $user = request()->user();

        if ($user->role === UserRole::Client) {
            return Inertia::render('dashboard', ['resumen' => null]);
        }

        $clientes = $this->clientesVisiblesPara($user)->with('formasCliente')->get();

        $porEstado = $clientes
            ->map(fn (User $cliente) => $this->estadoGeneralDe($cliente))
            ->countBy();

        return Inertia::render('dashboard', [
            'resumen' => [
                'total' => $clientes->count(),
                'sin_iniciar' => $porEstado->get('sin_iniciar', 0),
                'en_progreso' => $porEstado->get('en_progreso', 0),
                'completo' => $porEstado->get('completo', 0),
                ...$this->summary->resumenPara($clientes),
            ],
        ]);
    }
}
