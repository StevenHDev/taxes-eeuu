<?php

namespace App\Models;

use App\Enums\EstadoControlConversacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Estado de control (agente|humano) de una conversación de WhatsApp — ver
 * sección ESCALAMIENTO A HUMANO de docs/implementar_agente_n8n.md.
 *
 * @property int $id
 * @property string $telefono
 * @property int|null $cliente_id
 * @property EstadoControlConversacion $estado
 * @property int|null $tomado_por_user_id
 * @property Carbon|null $tomado_en
 */
#[Fillable(['telefono', 'cliente_id', 'estado', 'tomado_por_user_id', 'tomado_en'])]
class WhatsappControl extends Model
{
    protected $table = 'whatsapp_control';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoControlConversacion::class,
            'tomado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tomadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tomado_por_user_id');
    }

    public function esHumano(): bool
    {
        return $this->estado === EstadoControlConversacion::Humano;
    }

    public function tomar(User $preparador): void
    {
        $this->update([
            'estado' => EstadoControlConversacion::Humano,
            'tomado_por_user_id' => $preparador->id,
            'tomado_en' => now(),
        ]);
    }

    public function devolver(): void
    {
        $this->update([
            'estado' => EstadoControlConversacion::Agente,
            'tomado_por_user_id' => null,
            'tomado_en' => null,
        ]);
    }
}
