<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    use ManagesClientes;

    public function index(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('usuarios/index', [
            'usuarios' => User::query()
                ->with('preparer:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'role', 'preparer_id'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'role' => $u->role,
                    'preparer' => $u->preparer?->only(['id', 'name']),
                ]),
            'preparadores' => User::query()
                ->where('role', UserRole::Preparer)
                ->get(['id', 'name']),
        ]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        User::query()->create([
            ...$request->safe()->except('password'),
            'password' => Hash::make($request->validated('password')),
        ]);

        return back();
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $usuario->update([
            ...$request->safe()->except('password'),
            ...$request->validated('password') ? ['password' => Hash::make($request->validated('password'))] : [],
        ]);

        return back();
    }

    public function destroy(User $usuario): RedirectResponse
    {
        $this->authorize('delete', $usuario);

        $this->eliminarArchivosDe($usuario);
        $usuario->delete();

        return back();
    }
}
