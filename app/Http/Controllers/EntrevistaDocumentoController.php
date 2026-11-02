<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevisarDocumentoRequest;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\TransicionNoPermitida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * El preparador revisa los documentos del checklist desde la ficha del
 * cliente (F3.4). Las reglas de cada cambio viven en ChecklistDocumentos.
 */
class EntrevistaDocumentoController extends Controller
{
    public function update(RevisarDocumentoRequest $request, User $cliente, EntrevistaDocumento $item, ChecklistDocumentos $checklist): RedirectResponse
    {
        $texto = (string) $request->validated('texto');

        try {
            match ($request->validated('accion')) {
                'validar' => $checklist->validar($item, $request->user()),
                'observar' => $checklist->observar($item, $texto, $request->user()),
                'rechazar_no_aplica' => $checklist->rechazarNoAplica($item, $texto, $request->user()),
            };
        } catch (TransicionNoPermitida $e) {
            throw ValidationException::withMessages(['accion' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($request->validated('accion')) {
            'validar' => __('Documento validado.'),
            'observar' => __('Le devolvimos el documento al cliente con tu observación.'),
            default => __('Rechazaste el "no aplica": el cliente lo verá de nuevo como pendiente.'),
        }]);

        return back();
    }
}
