<?php

namespace App\Http\Controllers;

use App\Enums\FieldDataType;
use App\Enums\FieldMode;
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
        $taxYear = $request->taxYear();
        $campo = $request->campoNombre();
        $field = TaxFieldCatalog::find($taxYear, $forma, $campo);

        $this->eventos->corregirManualmente(
            cliente: $cliente,
            taxYear: $taxYear,
            forma: $forma,
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

        $request->validate([
            'forma' => ['required', 'string'],
            'tax_year' => ['required', 'integer', 'digits:4'],
        ]);

        $forma = (string) $request->query('forma');
        $taxYear = (int) $request->query('tax_year');

        $this->eventos->eliminarCampo($cliente, $taxYear, $forma, $campo, $request->user());

        return back();
    }

    public function historial(Request $request, User $cliente, string $campo): JsonResponse
    {
        $this->authorize('view', $cliente);

        $forma = (string) $request->query('forma');
        $taxYear = (int) $request->query('tax_year');

        $campoCliente = $this->buscarCampoCliente($cliente, $taxYear, $forma, $campo);

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

        $forma = (string) $request->query('forma');
        $taxYear = (int) $request->query('tax_year');

        $campoCliente = $this->buscarCampoCliente($cliente, $taxYear, $forma, $campo);

        CampoReveal::query()->create([
            'campo_cliente_id' => $campoCliente->id,
            'revealed_by_id' => $request->user()->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['valor' => $campoCliente->valor_texto]);
    }

    private function buscarCampoCliente(User $cliente, int $taxYear, string $forma, string $campo): CampoCliente
    {
        // Normaliza a la forma de almacenamiento: los campos únicos por cliente
        // viven bajo 'transversal', sin importar la forma que llegue en el request.
        $formaAlmacen = TaxFieldCatalog::formaAlmacen($taxYear, $campo, $forma);

        return CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $formaAlmacen)
            ->where('campo', $campo)
            ->where('tax_year', $taxYear)
            ->firstOrFail();
    }
}
