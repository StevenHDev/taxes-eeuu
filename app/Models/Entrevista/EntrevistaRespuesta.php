<?php

namespace App\Models\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una respuesta de la entrevista: por pregunta, y por persona en el bloque
 * C. El valor va siempre cifrado y con la forma que fija
 * App\Services\Entrevista\ValorRespuesta. Cada cambio deja una fila en
 * `entrevista_respuesta_cambios` (RespuestaEntrevistaObserver).
 *
 * @property int $id
 * @property int $entrevista_id
 * @property string $pregunta_codigo
 * @property int|null $persona_id
 * @property string|int|float|list<string> $valor
 * @property CanalRespuesta $canal
 * @property int|null $actor_id
 * @property Carbon $respondida_en
 */
#[Fillable(['entrevista_id', 'pregunta_codigo', 'persona_id', 'valor', 'canal', 'actor_id', 'respondida_en'])]
class EntrevistaRespuesta extends Model
{
    protected $table = 'entrevista_respuestas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => ValorCifrado::class,
            'canal' => CanalRespuesta::class,
            'respondida_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Entrevista, $this>
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class);
    }

    /**
     * @return BelongsTo<EntrevistaPersona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(EntrevistaPersona::class, 'persona_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return HasMany<EntrevistaRespuestaCambio, $this>
     */
    public function cambios(): HasMany
    {
        return $this->hasMany(EntrevistaRespuestaCambio::class, 'respuesta_id');
    }
}
