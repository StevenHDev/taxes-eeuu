<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se dispara una sola vez, al crear la cuenta de un cliente nuevo — desde
 * WhatsApp (ver App\Services\AgenteToolService::crearCliente()) o desde el
 * panel del preparador (ver App\Http\Controllers\ClienteController::store()).
 * La cuenta se crea siempre con una contraseña aleatoria que nadie conoce
 * (ver esos mismos métodos) — sin este aviso, el cliente nunca podría entrar
 * al portal seguro.
 *
 * Reusa el mismo mecanismo de token que "olvidé mi contraseña" (mismo
 * `password.reset`, mismo broker) en vez de inventar uno nuevo — la única
 * diferencia es el texto del correo (bienvenida, no recuperación) y que acá
 * el token se genera proactivamente, no a pedido del cliente.
 *
 * A propósito NUNCA se envía la contraseña real (ni la aleatoria) por
 * ningún medio — un link de un solo uso que expira es más seguro que un
 * secreto de texto plano viajando por correo/WhatsApp, que es justo el tipo
 * de canal inseguro que motivó construir este portal.
 */
class BienvenidaClientePortal extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ], false));

        return (new MailMessage)
            ->subject('Bienvenido a GlobalTax — activa tu cuenta del portal')
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line('Ya tenemos tu información para empezar tu declaración de impuestos. Antes de entrar al portal seguro, define la contraseña de tu cuenta.')
            ->action('Definir mi contraseña', $url)
            ->line('Este link es de un solo uso y expira por tu seguridad. Si expira antes de que lo uses, puedes pedir uno nuevo desde "¿Olvidaste tu contraseña?" en la pantalla de inicio de sesión.');
    }
}
