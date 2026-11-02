<?php

namespace App\Http\Controllers;

use App\Models\CampoCliente;
use App\Models\CampoReveal;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los datos del formulario anterior (`campos_cliente`) en la ficha: desde
 * F2.11 son histórico de solo lectura (historial y revelar). Se corrigen en
 * la sección Entrevista; la API externa (E3) sigue escribiendo por su lado.
 */
class CampoClienteController extends Controller
{
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
