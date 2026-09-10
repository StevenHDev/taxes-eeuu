<?php

namespace App\Jobs;

use App\Enums\EstadoControlConversacion;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Procesa un mensaje entrante de WhatsApp: idempotencia por MessageSid,
 * resuelve/crea el estado de control de la conversación, y guarda el mensaje.
 * La invocación al agente conversacional (Fase 3) se agrega dentro de este
 * mismo job, justo donde se indica más abajo — solo corre si la conversación
 * no está en modo `humano` (ver ESCALAMIENTO A HUMANO en
 * docs/implementar_agente_n8n.md).
 */
class ProcesarMensajeWhatsappJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload  Payload crudo del webhook de Twilio
     *                                         (MessageSid, From, Body, NumMedia, MediaUrl0.., etc.) — se conserva completo,
     *                                         sin whitelistear campos, porque la Fase 4 (extracción de documentos) todavía
     *                                         necesita leer los campos de media de acá.
     */
    public function __construct(private readonly array $payload) {}

    public function handle(): void
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

            // Fase 3: invocar aquí a AgenteConversacionalService con el historial de
            // whatsapp_mensajes para este teléfono, y volver a comprobar
            // WhatsappControl::esHumano() justo antes de enviar la respuesta (condición
            // de carrera — ver ESCALAMIENTO A HUMANO).
        } finally {
            $lock->release();
        }
    }

    private function normalizarTelefono(string $from): string
    {
        return str_starts_with($from, 'whatsapp:') ? substr($from, strlen('whatsapp:')) : $from;
    }
}
