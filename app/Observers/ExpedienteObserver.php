<?php

namespace App\Observers;

use App\Models\Consentimiento\Consentimiento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\Excepcion;
use App\Services\Expediente\EstadosExpediente;
use Illuminate\Database\Eloquent\Model;

/**
 * Pide recalcular el expediente cuando cambia algo que lo afecta: un ítem
 * del checklist, una excepción, la entrevista o la firma del consentimiento.
 * El recálculo corre después del commit y una sola vez por cliente y año
 * (EstadosExpediente::pedirRecalculo); este observer solo avisa.
 */
class ExpedienteObserver
{
    public function saved(Model $modelo): void
    {
        // El consentimiento solo se inserta: lo atiende created().
        if (! $modelo instanceof Entrevista && ! $modelo instanceof EntrevistaDocumento && ! $modelo instanceof Excepcion) {
            return;
        }

        $entrevista = $modelo instanceof Entrevista
            ? $modelo
            : Entrevista::query()->select(['id', 'user_id', 'tax_year'])->find($modelo->entrevista_id);

        if ($entrevista !== null) {
            app(EstadosExpediente::class)->pedirRecalculo($entrevista->user_id, $entrevista->tax_year);
        }
    }

    public function created(Model $modelo): void
    {
        // Solo la firma del consentimiento: los demás modelos se atienden en saved().
        if (! $modelo instanceof Consentimiento) {
            return;
        }

        $consentimiento = $modelo;

        app(EstadosExpediente::class)->pedirRecalculo($consentimiento->user_id, $consentimiento->tax_year);
    }
}
