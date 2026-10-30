<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivarCuentaRequest;
use App\Models\User;
use App\Services\Consentimiento\EnlaceActivacion;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Primera entrada desde el enlace que manda el agente por WhatsApp (F2.4):
 * el cliente crea su contraseña, queda con la sesión iniciada y pasa directo
 * al consentimiento. La pantalla no muestra ningún dato de la cuenta hasta
 * que la contraseña existe. Ver App\Services\Consentimiento\EnlaceActivacion.
 */
class ActivacionCuentaController extends Controller
{
    public function __construct(private readonly EnlaceActivacion $enlace) {}

    public function show(Request $request, User $usuario): Response
    {
        return Inertia::render('auth/activar-cuenta', [
            'vigente' => $this->enlace->pendiente($usuario),
            'accion' => $request->fullUrl(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(ActivarCuentaRequest $request, User $usuario): RedirectResponse
    {
        if (! $this->enlace->pendiente($usuario)) {
            return back()->withErrors(['enlace' => __('Este enlace ya se usó. Si olvidaste tu contraseña, usa "¿Olvidaste tu contraseña?" en el inicio de sesión.')]);
        }

        $usuario->forceFill([
            'password' => $request->validated('password'),
            'contrasena_definida_en' => now(),
        ])->save();

        event(new PasswordReset($usuario));

        Auth::login($usuario);
        $request->session()->regenerate();

        return to_route('portal.consentimiento');
    }
}
