<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al preparador (F5.6): el expediente de un cliente quedó listo para
 * preparar, o el cliente respondió una consulta. Lleva solo el nombre del
 * cliente, el estado y el enlace a la ficha; nada de la declaración.
 */
class AvisoExpedientePreparador extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $cliente,
        public readonly string $estado,
        public readonly bool $respondioConsulta,
        public readonly string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $asunto = $this->respondioConsulta
            ? __(':cliente respondió una consulta', ['cliente' => $this->cliente])
            : __('Listo para preparar: :cliente', ['cliente' => $this->cliente]);

        return (new MailMessage)
            ->subject($asunto)
            ->line(__('Estado del expediente: :estado.', ['estado' => $this->estado]))
            ->action(__('Abrir la ficha'), $this->url);
    }
}
