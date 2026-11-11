<?php

namespace App\Models\Expediente;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Historial de un dato anotado sin documento (F6 6.5): quién lo anotó, cambió
 * o retiró, cuándo, de qué a qué y por qué; o que lo reemplazó el documento
 * leído (actor nulo = la plataforma). Solo se inserta.
 *
 * @property int $id
 * @property int $dato_id
 * @property string $accion
 * @property string|null $valor_anterior
 * @property string|null $valor_nuevo
 * @property string|null $motivo
 * @property int|null $actor_id
 * @property Carbon $created_at
 */
#[Fillable(['dato_id', 'accion', 'valor_anterior', 'valor_nuevo', 'motivo', 'actor_id'])]
class ExpedienteDatoSinDocumentoCambio extends Model
{
    public const UPDATED_AT = null;

    public const ANOTADO = 'anotado';

    public const CAMBIADO = 'cambiado';

    public const RETIRADO = 'retirado';

    public const REEMPLAZADO = 'reemplazado';

    protected $table = 'expediente_dato_sin_documento_cambios';

    /** @var list<string> */
    protected $hidden = ['valor_anterior', 'valor_nuevo', 'motivo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_anterior' => 'encrypted',
            'valor_nuevo' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<ExpedienteDatoSinDocumento, $this>
     */
    public function dato(): BelongsTo
    {
        return $this->belongsTo(ExpedienteDatoSinDocumento::class, 'dato_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
