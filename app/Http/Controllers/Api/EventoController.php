<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventoRequest;
use App\Services\EventoRecoleccionService;
use Illuminate\Http\JsonResponse;

class EventoController extends Controller
{
    public function __construct(private readonly EventoRecoleccionService $eventos) {}

    public function store(EventoRequest $request): JsonResponse
    {
        $resultado = $this->eventos->procesar($request);

        return response()->json([
            'cliente_id' => $resultado['cliente']->id,
            'forma' => $resultado['campo_cliente']->forma,
            'forma_estado' => $resultado['forma_cliente']->estado,
            'campo' => $resultado['campo_cliente']->campo,
            'estado' => $resultado['campo_cliente']->estado,
        ], 201);
    }
}
