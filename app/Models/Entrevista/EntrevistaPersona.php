<?php

namespace App\Models\Entrevista;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una persona del bloque C (quien vivió con el cliente o a quien mantuvo).
 * La etiqueta no lleva datos sensibles ("Persona 1", o el parentesco):
 * nombre, SSN y fecha de nacimiento son respuestas cifradas.
 *
 * @property int $id
 * @property int $entrevista_id
 * @property int $orden
 * @property string $etiqueta
 */
#[Fillable(['entrevista_id', 'orden', 'etiqueta'])]
class EntrevistaPersona extends Model
{
    use SoftDeletes;

    protected $table = 'entrevista_personas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    /**
     * @return BelongsTo<Entrevista, $this>
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class);
    }
}
