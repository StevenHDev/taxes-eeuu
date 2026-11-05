<?php

namespace App\Events;

use App\Enums\Expediente\EstadoExpediente;
use App\Models\Expediente\Expediente;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un expediente cambió de estado. Lo escuchan los avisos (5.6a). Se despacha
 * después del commit; `actor` nulo = la plataforma.
 */
class ExpedienteCambioDeEstado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Expediente $expediente,
        public ?EstadoExpediente $anterior,
        public EstadoExpediente $nuevo,
        public ?User $actor,
    ) {}
}
