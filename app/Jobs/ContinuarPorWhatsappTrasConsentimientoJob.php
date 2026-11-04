<?php

namespace App\Jobs;

use App\Enums\RolMensajeWhatsapp;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use App\Services\Whatsapp\WhatsappChannel;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use App\Services\WhatsappAgent\EstadoConversacionResolver;
use App\Support\AgenteWhatsappUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Cuando el cliente firma el consentimiento en el portal, el agente le escribe
 * por WhatsApp para seguir con la entrevista: el cliente no tiene que avisar
 * que ya firmó. Solo si la conversación existe, el agente la lleva (no un
 * preparador) y el último mensaje del cliente es de las últimas 24 horas
 * (fuera de esa ventana Twilio rechaza el texto libre).
 */
class ContinuarPorWhatsappTrasConsentimientoJob implements ShouldQueue
{
    use Queueable;

    /** Lo que queda en el historial: el agente lo lee y sigue con la primera pregunta de la entrevista. */
    public const AVISO_FIRMA = '[El cliente firmó el consentimiento en el portal.]';

    /** Sin reintentos: un reintento podría mandar el primer mensaje dos veces. */
    public int $tries = 1;

    public function __construct(private readonly int $clienteId)
    {
        $this->afterCommit();
    }

    public function handle(AgenteConversacionalService $agente, WhatsappChannel $canal): void
    {
        $cliente = User::query()->find($this->clienteId);

        if ($cliente === null || ! EstadoConversacionResolver::entrevistaPorWhatsapp($cliente)) {
            return;
        }

        $control = WhatsappControl::query()->where('cliente_id', $cliente->id)->first();

        if ($control === null || $control->esHumano()) {
            return;
        }

        $telefono = $control->telefono;

        $ultimoDelCliente = WhatsappMensaje::query()
            ->where('telefono', $telefono)
            ->where('rol', RolMensajeWhatsapp::Cliente)
            ->latest('id')
            ->first();

        if ($ultimoDelCliente === null || $ultimoDelCliente->created_at->lt(now()->subHours(24))) {
            return;
        }

        WhatsappMensaje::query()->create([
            'telefono' => $telefono,
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => self::AVISO_FIRMA,
            'mensaje_externo_id' => 'consentimiento-'.Str::uuid(),
            'proveedor' => $ultimoDelCliente->proveedor,
        ]);

        $historial = WhatsappMensaje::query()->where('telefono', $telefono)->orderBy('id')->get();
        $resultado = $agente->responder($cliente, $historial, AgenteWhatsappUser::resolver(), $telefono);

        if ($control->fresh()->esHumano()) {
            return;
        }

        $idRespuesta = $canal->enviarTexto($telefono, $resultado['texto']);

        WhatsappMensaje::query()->create([
            'telefono' => $telefono,
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Agente,
            'contenido' => $resultado['texto'],
            'mensaje_externo_id' => $idRespuesta,
            'proveedor' => $ultimoDelCliente->proveedor,
            'prompt_version' => $resultado['prompt_version'],
        ]);
    }
}
