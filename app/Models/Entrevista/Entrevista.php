<?php

namespace App\Models\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * La entrevista de un cliente en un año fiscal (F2.2): una por cliente y
 * año. Qué preguntas se ven y qué activa cada respuesta lo decide
 * App\Services\Entrevista\MotorEntrevista; las respuestas solo se escriben
 * con App\Services\Entrevista\RespuestaGuardador.
 *
 * @property int $id
 * @property int $user_id
 * @property int $tax_year
 * @property EstadoEntrevista $estado
 * @property CanalRespuesta $canal_inicio
 * @property Carbon $iniciada_en
 * @property Carbon|null $confirmada_en
 * @property int|null $consentimiento_id
 * @property-read User $user
 * @property-read Collection<int, EntrevistaPersona> $personas
 * @property-read Collection<int, EntrevistaRespuesta> $respuestas
 */
#[Fillable(['user_id', 'tax_year', 'estado', 'canal_inicio', 'iniciada_en', 'confirmada_en', 'consentimiento_id'])]
class Entrevista extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'estado' => EstadoEntrevista::class,
            'canal_inicio' => CanalRespuesta::class,
            'iniciada_en' => 'datetime',
            'confirmada_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Las personas del bloque C que siguen en la entrevista (quitar una la
     * archiva).
     *
     * @return HasMany<EntrevistaPersona, $this>
     */
    public function personas(): HasMany
    {
        return $this->hasMany(EntrevistaPersona::class)->orderBy('orden');
    }

    /**
     * @return HasMany<EntrevistaRespuesta, $this>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(EntrevistaRespuesta::class);
    }
}
