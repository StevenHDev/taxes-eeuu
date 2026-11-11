<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expediente\AnotarSinDocumentoRequest;
use App\Models\Expediente\ExpedienteDatoSinDocumento;
use App\Models\User;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Expediente\DatosSinDocumento;
use App\Services\Expediente\EstadosExpediente;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * El respaldo manual sin documento del borrador (F6 6.5): anotar, cambiar y
 * retirar un monto que el cliente no tiene en ningún papel. Solo el
 * preparador del cliente y el administrador; el cliente recibe 403. Las
 * reglas viven en DatosSinDocumento. Los errores nunca repiten el valor.
 */
class DatosSinDocumentoController extends Controller
{
    public function __construct(private readonly DatosSinDocumento $datos) {}

    public function store(AnotarSinDocumentoRequest $request, User $cliente, EstadosExpediente $estados): RedirectResponse
    {
        $expediente = $estados->recalcular($cliente, $request->integer('tax_year'));

        return $this->guardar('clave', __('Anotamos el dato sin documento.'), fn () => $this->datos->anotar(
            $expediente,
            (string) $request->validated('clave'),
            $request->validated('persona_id') === null ? null : (int) $request->validated('persona_id'),
            (string) $request->validated('valor'),
            (string) $request->validated('motivo'),
            (string) $request->validated('fuente'),
            $request->validated('fuente_fecha'),
            $request->validated('fuente_canal'),
            $request->user(),
        ));
    }

    public function update(Request $request, User $cliente, ExpedienteDatoSinDocumento $dato): RedirectResponse
    {
        $this->autorizar($cliente, $dato);

        $validados = $request->validate([
            'valor' => ['required', 'string', 'max:30'],
            'motivo' => ['required', 'string', 'max:1000'],
        ], ['motivo.required' => __('Escribe por qué cambias el dato.')], ['valor' => __('monto'), 'motivo' => __('motivo')]);

        return $this->guardar('valor', __('Cambiamos el dato sin documento.'), fn () => $this->datos->cambiar($dato, $validados['valor'], $validados['motivo'], $request->user()));
    }

    public function destroy(Request $request, User $cliente, ExpedienteDatoSinDocumento $dato): RedirectResponse
    {
        $this->autorizar($cliente, $dato);

        $validados = $request->validate([
            'motivo' => ['required', 'string', 'max:1000'],
        ], ['motivo.required' => __('Escribe por qué retiras el dato.')], ['motivo' => __('motivo')]);

        return $this->guardar('motivo', __('Retiramos el dato sin documento.'), fn () => $this->datos->retirar($dato, $validados['motivo'], $request->user()));
    }

    /**
     * Permiso sobre el cliente (403) y el dato de este cliente (404).
     */
    private function autorizar(User $cliente, ExpedienteDatoSinDocumento $dato): void
    {
        $this->authorize('update', $cliente);

        abort_unless($dato->expediente()->where('user_id', $cliente->id)->exists(), 404);
    }

    private function guardar(string $campo, string $aviso, Closure $escribir): RedirectResponse
    {
        try {
            $escribir();
        } catch (TransicionNoPermitida $e) {
            // Los mensajes del servicio son textos fijos: nunca llevan el valor ni el motivo.
            throw ValidationException::withMessages([$campo => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $aviso]);

        return back();
    }
}
