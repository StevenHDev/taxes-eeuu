<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiAbility;
use App\Http\Controllers\Controller;
use App\Services\AgenteToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Catálogo agente-facing, sin depender de un cliente concreto — distinto de
 * `App\Http\Controllers\CatalogoController` (panel admin de `/catalogo`).
 */
class CatalogoController extends Controller
{
    public function __construct(private readonly AgenteToolService $tools) {}

    /**
     * Los 18 documentos opcionales que ya no se preguntan proactivamente en
     * `GET /api/clientes/{cliente}/pendientes` (ver
     * `TaxFieldCatalog::documentosExtra()`) — el agente conversacional
     * externo consulta esto de forma reactiva cuando el cliente menciona o
     * sube un documento que no reconoce, para validar que es un campo real
     * del catálogo y obtener su shape exacto antes de invocar
     * guardar_campo_cliente.
     */
    public function documentosExtra(Request $request): JsonResponse
    {
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        // Acción de lectura consultada por una integración externa: tax_year
        // explícito siempre, igual que el resto del camino del agente.
        $request->validate(['tax_year' => ['required', 'integer', 'digits:4']]);

        return response()->json($this->tools->documentosExtra((int) $request->query('tax_year')));
    }

    private function ensureAbility(Request $request, ApiAbility $ability): void
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can($ability->value)) {
            abort(403, 'El token no tiene la ability requerida: '.$ability->value);
        }
    }
}
