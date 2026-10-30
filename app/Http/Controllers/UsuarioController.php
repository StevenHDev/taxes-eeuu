<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use App\Services\UsuarioRetencionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioRetencionService $retencion,
    ) {}

    public function index(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('usuarios/index', [
            // Incluye los archivados, para poder restaurarlos.
            'usuarios' => User::withTrashed()
                ->with('preparer:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'role', 'preparer_id', 'es_prueba', 'entrevista_catalogo', 'deleted_at'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'role' => $u->role,
                    'preparer' => $u->preparer?->only(['id', 'name']),
                    'es_prueba' => $u->es_prueba,
                    'entrevista_catalogo' => $u->entrevista_catalogo,
                    'archivado' => $u->trashed(),
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

    /**
     * Archiva: el usuario deja de verse y no puede entrar, pero sus datos se
     * conservan (F0.3).
     */
    public function destroy(User $usuario): RedirectResponse
    {
        $this->authorize('delete', $usuario);

        $this->retencion->archivar($usuario);

        return back();
    }

    public function restore(User $usuario): RedirectResponse
    {
        $this->authorize('restore', $usuario);

        $this->retencion->restaurar($usuario);

        return back();
    }

    /**
     * Marca o desmarca un usuario de prueba. Queda en la bitácora por el
     * AuditoriaObserver (campo `es_prueba`).
     */
    public function marcarPrueba(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('marcarPrueba', $usuario);

        $validado = $request->validate(['es_prueba' => ['required', 'boolean']]);

        $usuario->forceFill(['es_prueba' => $validado['es_prueba']])->save();

        return back();
    }

    /**
     * Pasa un cliente a la entrevista nueva sobre el catálogo fiscal (F2.2)
     * o lo devuelve al formulario actual. Queda en la bitácora por el
     * AuditoriaObserver (campo `entrevista_catalogo`).
     */
    public function marcarEntrevistaCatalogo(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('marcarEntrevistaCatalogo', $usuario);

        $validado = $request->validate(['entrevista_catalogo' => ['required', 'boolean']]);

        $usuario->forceFill(['entrevista_catalogo' => $validado['entrevista_catalogo']])->save();

        return back();
    }

    /**
     * Solo para usuarios de prueba: borra todo, con la opción de borrar
     * también su conversación de WhatsApp.
     */
    public function forceDestroy(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('forceDelete', $usuario);

        $this->retencion->eliminarDefinitivamente($usuario, $request->boolean('eliminar_conversacion_whatsapp'));

        return to_route($usuario->role === UserRole::Client ? 'clientes.index' : 'usuarios.index');
    }
}
