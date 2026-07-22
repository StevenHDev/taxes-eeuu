<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiAbility;
use App\Enums\FieldDataType;
use App\Enums\FieldMode;
use App\Enums\TaxForm;
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

        $forma = TaxForm::from((string) $request->query('forma'));

        $campoCliente = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $forma->value)
            ->where('campo', $campo)
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
        $campo = $request->campoNombre();
        $field = TaxFieldCatalog::find($forma->value, $campo);

        $resultado = $this->eventos->corregirManualmente(
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

        return response()->json([
            'campo' => $resultado['campo_cliente']->campo,
            'estado' => $resultado['campo_cliente']->estado,
        ]);
    }

    public function destroy(Request $request, User $cliente, string $campo): JsonResponse
    {
        $this->authorize('update', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesWrite);

        $forma = TaxForm::from((string) $request->query('forma'));

        $this->eventos->eliminarCampo($cliente, $forma->value, $campo, $request->user());

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
