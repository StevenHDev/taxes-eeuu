<?php

namespace App\Jobs;

use App\Enums\OrigenAnalisisMetaAgente;
use App\Models\User;
use App\Services\MetaAgente\ConversacionAnalizadorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Análisis puntual de UNA conversación, disparado desde el panel (ver
 * MetaAgenteController::store) — siempre analiza la conversación completa
 * (sin `desde`), a diferencia de la corrida automática diaria, que solo mira
 * la actividad nueva desde el último reporte de cada teléfono.
 */
class AnalizarConversacionAgenteJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $telefono,
        private readonly ?int $disparadoPorUsuarioId,
    ) {}

    public function handle(ConversacionAnalizadorService $analizador): void
    {
        $disparadoPor = $this->disparadoPorUsuarioId !== null
            ? User::query()->find($this->disparadoPorUsuarioId)
            : null;

        $analizador->analizar($this->telefono, OrigenAnalisisMetaAgente::Manual, $disparadoPor);
    }
}
