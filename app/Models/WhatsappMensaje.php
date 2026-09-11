<?php

namespace App\Models;

use App\Enums\RolMensajeWhatsapp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un mensaje de la conversación de WhatsApp con un cliente (o un teléfono
 * todavía sin cuenta) — reemplaza la lectura vía Supabase de
 * SupabaseWhatsappConversationService.
 *
 * @property int $id
 * @property string $telefono
 * @property int|null $cliente_id
 * @property RolMensajeWhatsapp $rol
 * @property string $contenido
 * @property string|null $mensaje_externo_id
 * @property string|null $proveedor
 * @property int|null $prompt_version
 */
#[Fillable(['telefono', 'cliente_id', 'rol', 'contenido', 'mensaje_externo_id', 'proveedor', 'prompt_version'])]
class WhatsappMensaje extends Model
{
    protected $table = 'whatsapp_mensajes';

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
}
