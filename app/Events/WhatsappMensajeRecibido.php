<?php

namespace App\Events;

use App\Models\WhatsappMensaje;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara cada vez que se guarda un WhatsappMensaje (entrante del
 * cliente, respuesta del agente, o envío manual de un preparador — ver
 * WhatsappMensajeObserver) para que la bandeja/conversación del panel se
 * actualice en vivo, sin depender de volver a hacer fetch. `ShouldBroadcastNow`
 * (no encolado) porque ya hay un worker de cola separado y no queremos que un
 * backlog ahí retrase la actualización en tiempo real.
 */
class WhatsappMensajeRecibido implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly WhatsappMensaje $mensaje) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        // Canal por teléfono (no por cliente_id): puede no existir cuenta
        // todavía (fase de verificación) — el "+" no es válido en un nombre
        // de canal de Pusher/Reverb, se recorta.
        return [new PrivateChannel('whatsapp.'.ltrim($this->mensaje->telefono, '+'))];
    }

    public function broadcastAs(): string
    {
        return 'mensaje.nuevo';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->mensaje->id,
            'telefono' => $this->mensaje->telefono,
            'cliente_id' => $this->mensaje->cliente_id,
            'rol' => $this->mensaje->rol->value,
            'contenido' => $this->mensaje->contenido,
            'proveedor' => $this->mensaje->proveedor,
            'created_at' => $this->mensaje->created_at?->toISOString(),
        ];
    }
}
