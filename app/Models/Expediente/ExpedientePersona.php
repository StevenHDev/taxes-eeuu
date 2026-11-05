<?php

namespace App\Models\Expediente;

use App\Enums\Expediente\ClasificacionPersona;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Decisión del preparador sobre una persona del bloque C, con su motivo.
 *
 * @property int $id
 * @property int $expediente_id
 * @property int $persona_id
 * @property ClasificacionPersona $clasificacion
 * @property string $motivo
 * @property int|null $decidido_por
 * @property Carbon $decidido_en
 * @property-read Expediente $expediente
 * @property-read EntrevistaPersona $persona
 * @property-read User|null $decisor
 */
#[Fillable(['expediente_id', 'persona_id', 'clasificacion', 'motivo', 'decidido_por', 'decidido_en'])]
class ExpedientePersona extends Model
{
    protected $table = 'expediente_personas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clasificacion' => ClasificacionPersona::class,
            'decidido_en' => 'datetime',
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
     * @return BelongsTo<EntrevistaPersona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(EntrevistaPersona::class, 'persona_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por');
    }
}
