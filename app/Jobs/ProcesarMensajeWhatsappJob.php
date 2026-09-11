<?php

namespace App\Jobs;

use App\Enums\EstadoControlConversacion;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use App\Services\Whatsapp\TwilioWhatsappClient;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use App\Support\AgenteWhatsappUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Procesa un mensaje entrante de WhatsApp: idempotencia por MessageSid,
 * resuelve/crea el estado de control de la conversación, guarda el mensaje,
 * invoca al agente conversacional (si la conversación no está en modo
 * `humano` — ver ESCALAMIENTO A HUMANO en docs/implementar_agente_n8n.md), y
 * envía + persiste la respuesta.
 *
 * Límite conocido, aceptado por ahora: si el job falla DESPUÉS de guardar el
 * mensaje entrante pero ANTES de terminar de enviar la respuesta (ej. Twilio
 * caído), un reintento del propio job (no un reintento de Twilio) se
 * detendría en el chequeo de idempotencia de abajo sin generar ni enviar la
 * respuesta pendiente — requeriría un estado explícito de "respuesta ya
 * enviada" (patrón outbox) para cerrarse del todo; se documenta como deuda
 * conocida en vez de resolverse acá.
 */
class ProcesarMensajeWhatsappJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload  Payload crudo del webhook de Twilio
     *                                         (MessageSid, From, Body, NumMedia, MediaUrl0.., etc.) — se conserva completo,
     *                                         sin whitelistear campos, porque la Fase 4 (extracción de documentos) todavía
     *                                         necesita leer los campos de media de acá (esa conexión, mensaje con media →
     *                                         DocumentoExtraccionService → guardar_campo_cliente con archivo, queda como
     *                                         siguiente paso: hoy este job solo pasa mensajes de texto al agente).
     */
    public function __construct(private readonly array $payload) {}

    public function handle(AgenteConversacionalService $agente, TwilioWhatsappClient $twilio): void
    {
        $messageSid = (string) ($this->payload['MessageSid'] ?? '');

        if ($messageSid === '') {
            return;
        }

        // Reintentos de Twilio (mismo MessageSid) no deben duplicar el mensaje ni
        // procesarse dos veces en paralelo — lock de caché + la columna única de
        // whatsapp_mensajes.twilio_message_sid como respaldo final.
        $lock = Cache::lock("whatsapp-mensaje-procesado:{$messageSid}", 10);

        if (! $lock->get()) {
            return;
        }

        try {
            if (WhatsappMensaje::query()->where('twilio_message_sid', $messageSid)->exists()) {
                return;
            }

            $telefono = $this->normalizarTelefono((string) ($this->payload['From'] ?? ''));

            $control = WhatsappControl::query()->firstOrCreate(
                ['telefono' => $telefono],
                [
                    'estado' => EstadoControlConversacion::Agente,
                    'cliente_id' => User::query()
                        ->where('role', UserRole::Client)
                        ->where('phone', $telefono)
                        ->value('id'),
                ],
            );

            WhatsappMensaje::query()->create([
                'telefono' => $telefono,
                'cliente_id' => $control->cliente_id,
                'rol' => RolMensajeWhatsapp::Cliente,
                'contenido' => (string) ($this->payload['Body'] ?? ''),
                'twilio_message_sid' => $messageSid,
            ]);

            if ($control->esHumano()) {
                return;
            }

            $cliente = $control->cliente_id ? User::query()->whereKey($control->cliente_id)->first() : null;
            $historial = WhatsappMensaje::query()->where('telefono', $telefono)->orderBy('id')->get();

            $resultado = $agente->responder($cliente, $historial, AgenteWhatsappUser::resolver());

            // crear_cliente_taxes puede haber corrido a mitad del turno — deja
            // el vínculo de la conversación con el cliente recién creado, en
            // vez de esperar a que un mensaje futuro lo resuelva por teléfono.
            if ($resultado['cliente'] !== null && $resultado['cliente']->id !== $control->cliente_id) {
                $control->update(['cliente_id' => $resultado['cliente']->id]);
            }

            // Condición de carrera: si un preparador tomó control mientras se
            // generaba la respuesta, no se envía por encima de él — se
            // vuelve a leer de la base, no del objeto en memoria de arriba.
            if ($control->fresh()->esHumano()) {
                return;
            }

            $sidRespuesta = $twilio->enviarTexto($telefono, $resultado['texto']);

            WhatsappMensaje::query()->create([
                'telefono' => $telefono,
                'cliente_id' => $resultado['cliente']?->id,
                'rol' => RolMensajeWhatsapp::Agente,
                'contenido' => $resultado['texto'],
                'twilio_message_sid' => $sidRespuesta,
                'prompt_version' => $resultado['prompt_version'],
            ]);
        } finally {
            $lock->release();
        }
    }

    private function normalizarTelefono(string $from): string
    {
        return str_starts_with($from, 'whatsapp:') ? substr($from, strlen('whatsapp:')) : $from;
    }
}
