<?php

namespace App\Models\Lectura;

use App\Models\Entrevista\ValorCifrado;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Historial de un dato leído: quién lo corrigió, cuándo, de qué a qué y por
 * qué (documento madre, 6.1.3). Solo se inserta.
 *
 * @property int $id
 * @property int $dato_id
 * @property string|int|float|list<string>|null $valor_anterior
 * @property string|int|float|list<string>|null $valor_nuevo
 * @property string $motivo
 * @property int|null $actor_id
 * @property Carbon $created_at
 */
#[Fillable(['dato_id', 'valor_anterior', 'valor_nuevo', 'motivo', 'actor_id'])]
class DocumentoDatoCambio extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'documento_dato_cambios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_anterior' => ValorCifrado::class,
            'valor_nuevo' => ValorCifrado::class,
        ];
    }

    /**
     * @return BelongsTo<DocumentoDato, $this>
     */
    public function dato(): BelongsTo
    {
        return $this->belongsTo(DocumentoDato::class, 'dato_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
