<?php

namespace App\Models;

use App\Enums\AccionAuditoria;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Un evento de la bitácora general de la plataforma — ver la migración
 * `create_bitacora_actividad_table` para el porqué de no guardar valores de
 * campos acá (esa es responsabilidad exclusiva de `HistorialCambio`).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_nombre
 * @property string|null $actor_email
 * @property AccionAuditoria $accion
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string|null $etiqueta
 * @property array<int, string>|null $campos_afectados
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
#[Fillable([
    'actor_id',
    'actor_nombre',
    'actor_email',
    'accion',
    'auditable_type',
    'auditable_id',
    'etiqueta',
    'campos_afectados',
    'ip_address',
])]
class BitacoraActividad extends Model
{
    protected $table = 'bitacora_actividad';

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accion' => AccionAuditoria::class,
            'campos_afectados' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
