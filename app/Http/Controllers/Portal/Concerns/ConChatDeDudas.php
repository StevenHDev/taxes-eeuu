<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\PortalMensaje;
use App\Models\User;
use Inertia\DeferProp;
use Inertia\Inertia;

/**
 * El historial del chat de dudas que va al costado de la entrevista
 * (F2.5). Llega diferido: el paso se muestra sin esperarlo.
 */
trait ConChatDeDudas
{
    protected function mensajesDelChat(User $cliente): DeferProp
    {
        return Inertia::defer(fn () => PortalMensaje::query()
            ->where('cliente_id', $cliente->id)
            ->orderBy('id')
            ->get()
            ->map(fn (PortalMensaje $m) => [
                'id' => $m->id,
                'rol' => $m->rol->value,
                'contenido' => $m->contenido,
                'created_at' => $m->created_at,
            ]));
    }
}
