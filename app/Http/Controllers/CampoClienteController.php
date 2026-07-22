<?php

namespace App\Http\Controllers;

use App\Enums\FieldDataType;
use App\Enums\FieldMode;
use App\Enums\TaxForm;
use App\Http\Requests\CampoClienteUpdateRequest;
use App\Models\CampoCliente;
use App\Models\CampoReveal;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Services\EventoRecoleccionService;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampoClienteController extends Controller
{
    public function __construct(private readonly EventoRecoleccionService $eventos) {}

    public function update(CampoClienteUpdateRequest $request, User $cliente): RedirectResponse
    {
        $forma = $request->forma();
        $campo = $request->campoNombre();
        $field = TaxFieldCatalog::find($forma->value, $campo);

        $this->eventos->corregirManualmente(
            cliente: $cliente,
            forma: $forma->value,
            campo: $campo,
            tipoCampo: $field['tipo']->value,
            modo: FieldMode::from($request->validated('modo')),
            tipoDato: $request->validated('tipo_dato') ? FieldDataType::from($request->validated('tipo_dato')) : null,
            contenido: $request->validated('contenido'),
            file: $request->file('file'),
            nombreOriginal: $request->validated('nombre_original'),
            actor: $request->user(),
        );

        return back();
    }

    public function destroy(Request $request, User $cliente, string $campo): RedirectResponse
    {
        $this->authorize('update', $cliente);

        $forma = TaxForm::from((string) $request->query('forma'));

        $this->eventos->eliminarCampo($cliente, $forma->value, $campo, $request->user());

        return back();
    }

    public function historial(Request $request, User $cliente, string $campo): JsonResponse
    {
        $this->authorize('view', $cliente);

        $forma = TaxForm::from((string) $request->query('forma'));

        $campoCliente = $this->buscarCampoCliente($cliente, $forma, $campo);

        return response()->json([
            'historial' => $campoCliente->historial()->get()->map(fn (HistorialCambio $h) => [
                'valor_anterior' => $h->valor_anterior,
                'valor_nuevo' => $h->valor_nuevo,
                'source' => $h->source,
                'modificado_por' => $h->modificadoPor?->name,
                'created_at' => $h->created_at,
            ]),
        ]);
    }

    public function reveal(Request $request, User $cliente, string $campo): JsonResponse
    {
        $this->authorize('view', $cliente);

        $forma = TaxForm::from((string) $request->query('forma'));

        $campoCliente = $this->buscarCampoCliente($cliente, $forma, $campo);

        CampoReveal::query()->create([
            'campo_cliente_id' => $campoCliente->id,
            'revealed_by_id' => $request->user()->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['valor' => $campoCliente->valor_texto]);
    }

    private function buscarCampoCliente(User $cliente, TaxForm $forma, string $campo): CampoCliente
    {
        return CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $forma->value)
            ->where('campo', $campo)
            ->firstOrFail();
    }
}
