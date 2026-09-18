<?php

namespace App\Models;

use App\Enums\OrigenAnalisisMetaAgente;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $telefono
 * @property int|null $cliente_id
 * @property Carbon $rango_desde
 * @property Carbon $rango_hasta
 * @property int $mensajes_analizados
 * @property int|null $prompt_version
 * @property string $modelo
 * @property array<int, array<string, mixed>> $hallazgos
 * @property OrigenAnalisisMetaAgente $origen
 * @property int|null $disparado_por_usuario_id
 */
#[Fillable([
    'telefono',
    'cliente_id',
    'rango_desde',
    'rango_hasta',
    'mensajes_analizados',
    'prompt_version',
    'modelo',
    'hallazgos',
    'origen',
    'disparado_por_usuario_id',
])]
class MetaAgenteReporte extends Model
{
    protected $table = 'meta_agente_reportes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rango_desde' => 'datetime',
            'rango_hasta' => 'datetime',
            'hallazgos' => 'array',
            'origen' => OrigenAnalisisMetaAgente::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disparadoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disparado_por_usuario_id');
    }
}
