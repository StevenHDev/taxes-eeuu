<?php

namespace App\Observers;

use App\Models\Entrevista\EntrevistaRespuesta;

/**
 * Historial de las respuestas de la entrevista (F2.2): cada cambio de valor
 * deja una fila con el valor anterior y el nuevo, cifrados, el canal y
 * quién lo hizo. La primera respuesta no deja historial: ya está en la
 * propia fila (`canal`, `actor_id`, `respondida_en`).
 */
class RespuestaEntrevistaObserver
{
    public function updated(EntrevistaRespuesta $respuesta): void
    {
        if (! $respuesta->wasChanged('valor')) {
            return;
        }

        $respuesta->cambios()->create([
            'valor_anterior' => (new EntrevistaRespuesta)->setRawAttributes(['valor' => $respuesta->getRawOriginal('valor')])->valor,
            'valor_nuevo' => $respuesta->valor,
            'canal' => $respuesta->canal,
            'actor_id' => $respuesta->actor_id,
        ]);
    }
}
