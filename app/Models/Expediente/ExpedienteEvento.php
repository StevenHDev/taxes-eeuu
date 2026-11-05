<?php

namespace App\Models\Expediente;

use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bitácora del expediente: solo se inserta (nunca se actualiza ni se borra a
 * mano). Es la fuente de las fechas de cada estado y de la línea de tiempo.
 * `datos` y `motivo` nunca llevan SSN, montos ni datos del bloque C.
 *
 * @property int $id
 * @property int $expediente_id
 * @property TipoEventoExpediente $tipo
 * @property EstadoExpediente|null $estado_anterior
 * @property EstadoExpediente|null $estado_nuevo
 * @property int|null $actor_id
 * @property string|null $motivo
 * @property array<string, mixed>|null $datos
 * @property Carbon $created_at
 * @property-read Expediente $expediente
 * @property-read User|null $actor
 */
#[Fillable(['expediente_id', 'tipo', 'estado_anterior', 'estado_nuevo', 'actor_id', 'motivo', 'datos'])]
class ExpedienteEvento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'expediente_eventos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoExpediente::class,
            'estado_anterior' => EstadoExpediente::class,
            'estado_nuevo' => EstadoExpediente::class,
            'datos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Expediente, $this>
     */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
