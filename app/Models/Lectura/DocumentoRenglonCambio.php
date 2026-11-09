<?php

namespace App\Models\Lectura;

use App\Models\Entrevista\ValorCifrado;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Historial de un renglón de los registros (F6 6.3b): quién cambió el monto,
 * la categoría o el sentido, cuándo, de qué a qué y por qué. Solo se inserta.
 * Mismo patrón que DocumentoDatoCambio, con `campo` porque un renglón tiene
 * varios campos corregibles.
 *
 * @property int $id
 * @property int $renglon_id
 * @property string $campo
 * @property string|int|float|list<string>|null $valor_anterior
 * @property string|int|float|list<string>|null $valor_nuevo
 * @property string $motivo
 * @property int|null $actor_id
 * @property Carbon $created_at
 */
#[Fillable(['renglon_id', 'campo', 'valor_anterior', 'valor_nuevo', 'motivo', 'actor_id'])]
class DocumentoRenglonCambio extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'documento_renglon_cambios';

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
     * @return BelongsTo<DocumentoRenglon, $this>
     */
    public function renglon(): BelongsTo
    {
        return $this->belongsTo(DocumentoRenglon::class, 'renglon_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
