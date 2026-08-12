<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventoRequest;
use App\Services\EventoRecoleccionService;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\JsonResponse;

class EventoController extends Controller
{
    public function __construct(private readonly EventoRecoleccionService $eventos) {}

    public function store(EventoRequest $request): JsonResponse
    {
        $resultado = $this->eventos->procesar($request);

        $taxYear = (int) $request->validated('tax_year');
        $campo = (string) $request->validated('campo');

        return response()->json([
            'cliente_id' => $resultado['cliente']->id,
            // La forma del evento tal como la envió el agente (los campos únicos por
            // cliente se guardan bajo 'transversal', pero se responde la del evento).
            'forma' => $request->validated('forma'),
            'forma_estado' => $resultado['forma_cliente']?->estado,
            'campo' => $resultado['campo_cliente']->campo,
            'estado' => $resultado['campo_cliente']->estado,
            // Se repite acá el mismo `revela` que ya traía esta entrada en
            // consultar_pendientes_cliente — el agente no debería necesitar
            // "recordarlo" de una respuesta 1-2 turnos atrás para saber que debe
            // seguir guardando los campos que este documento revela. Encontrado
            // en producción: con prompts largos, modelos más chicos (ej. gpt-5-mini)
            // pierden esa referencia y solo guardan el documento principal.
            'revela' => TaxFieldCatalog::revelaPara($taxYear, $campo),
            // Confirma qué quedó guardado de `revelados` (si el agente envió
            // alguno en esta misma llamada) — útil para depurar si alguno
            // resultó "invalido" en vez de asumir que se guardó bien.
            'revelados' => collect($resultado['revelados'])->map(fn (array $r) => [
                'forma' => $r['campo_cliente']->forma,
                'campo' => $r['campo_cliente']->campo,
                'estado' => $r['campo_cliente']->estado,
            ])->all(),
        ], 201);
    }
}
