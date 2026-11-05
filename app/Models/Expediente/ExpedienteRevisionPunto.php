<?php

namespace App\Models\Expediente;

use App\Enums\Expediente\PuntoRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Confirmación de un punto manual del checklist de revisión.
 *
 * @property int $id
 * @property int $expediente_id
 * @property PuntoRevision $punto
 * @property int|null $confirmado_por
 * @property Carbon|null $confirmado_en
 * @property string|null $nota
 * @property-read Expediente $expediente
 * @property-read User|null $confirmador
 */
#[Fillable(['expediente_id', 'punto', 'confirmado_por', 'confirmado_en', 'nota'])]
class ExpedienteRevisionPunto extends Model
{
    protected $table = 'expediente_revision_puntos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'punto' => PuntoRevision::class,
            'confirmado_en' => 'datetime',
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
    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }
}
