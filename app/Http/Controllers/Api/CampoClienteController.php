<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiAbility;
use App\Enums\FieldDataType;
use App\Enums\FieldMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\CampoClienteUpdateRequest;
use App\Models\CampoCliente;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Services\EventoRecoleccionService;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CampoClienteController extends Controller
{
    public function __construct(private readonly EventoRecoleccionService $eventos) {}

    public function historial(Request $request, User $cliente, string $campo): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        $taxYear = (int) $request->query('tax_year');

        // Normaliza a la forma de almacenamiento: los campos únicos por cliente
        // viven bajo 'transversal', sin importar la forma que llegue en el request.
        $forma = TaxFieldCatalog::formaAlmacen($taxYear, $campo, (string) $request->query('forma'));

        $campoCliente = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $forma)
            ->where('campo', $campo)
            ->where('tax_year', $taxYear)
            ->firstOrFail();

        return response()->json([
            'data' => $campoCliente->historial()->get()->map(fn (HistorialCambio $h) => [
                'valor_anterior' => $h->valor_anterior,
                'valor_nuevo' => $h->valor_nuevo,
                'source' => $h->source,
                'modificado_por' => $h->modificadoPor?->name,
                'created_at' => $h->created_at,
            ]),
        ]);
    }

    public function update(CampoClienteUpdateRequest $request, User $cliente): JsonResponse
    {
        $forma = $request->forma();
        $taxYear = $request->taxYear();
        $campo = $request->campoNombre();
        $field = TaxFieldCatalog::find($taxYear, $forma, $campo);

        $resultado = $this->eventos->corregirManualmente(
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

        return response()->json([
            'campo' => $resultado['campo_cliente']->campo,
            'estado' => $resultado['campo_cliente']->estado,
        ]);
    }

    public function destroy(Request $request, User $cliente, string $campo): JsonResponse
    {
        $this->authorize('update', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesWrite);

        $request->validate([
            'forma' => ['required', 'string'],
            'tax_year' => ['required', 'integer', 'digits:4'],
        ]);

        $forma = (string) $request->query('forma');
        $taxYear = (int) $request->query('tax_year');

        $this->eventos->eliminarCampo($cliente, $taxYear, $forma, $campo, $request->user());

        return response()->json(status: 204);
    }

    private function ensureAbility(Request $request, ApiAbility $ability): void
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can($ability->value)) {
            abort(403, 'El token no tiene la ability requerida: '.$ability->value);
        }
    }
}
