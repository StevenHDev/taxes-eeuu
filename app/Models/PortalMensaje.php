<?php

namespace App\Models;

use App\Contracts\MensajeConversacion;
use App\Enums\RolMensajeWhatsapp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un mensaje del chat del portal seguro del cliente — ver
 * App\Http\Controllers\PortalChatController y App\Contracts\MensajeConversacion
 * (misma AgenteConversacionalService que WhatsappMensaje, canal distinto).
 *
 * @property int $id
 * @property int $cliente_id
 * @property RolMensajeWhatsapp $rol
 * @property string $contenido
 * @property int|null $prompt_version
 */
#[Fillable(['cliente_id', 'rol', 'contenido', 'prompt_version'])]
class PortalMensaje extends Model implements MensajeConversacion
{
    protected $table = 'portal_mensajes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rol' => RolMensajeWhatsapp::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function rolConversacion(): RolMensajeWhatsapp
    {
        return $this->rol;
    }

    public function contenidoConversacion(): string
    {
        return $this->contenido;
    }
}
