<?php

namespace App\Models\Entrevista;

use App\Enums\Entrevista\EstadoDocumento;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un ítem del checklist de documentos (F3.2): un documento activado por la
 * entrevista, por persona y por fuente, o un "otro documento" del cliente.
 * Los cambios de estado pasan solo por App\Services\Entrevista\ChecklistDocumentos.
 *
 * @property int $id
 * @property int $entrevista_id
 * @property string $documento_codigo
 * @property int|null $persona_id
 * @property int $numero
 * @property bool $agregado_por_cliente
 * @property string|null $descripcion
 * @property EstadoDocumento $estado
 * @property string|null $motivo_no_aplica
 * @property string|null $observacion
 * @property string|null $nota_cliente
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_en
 * @property Carbon|null $retirado_en
 */
#[Fillable(['entrevista_id', 'documento_codigo', 'persona_id', 'numero', 'agregado_por_cliente', 'descripcion', 'estado', 'motivo_no_aplica', 'observacion', 'nota_cliente', 'revisado_por', 'revisado_en', 'retirado_en'])]
class EntrevistaDocumento extends Model
{
    /** El código de los documentos que el cliente agrega por fuera de la lista. */
    public const OTRO = 'otro';

    protected $table = 'entrevista_documentos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'agregado_por_cliente' => 'boolean',
            'estado' => EstadoDocumento::class,
            'revisado_en' => 'datetime',
            'retirado_en' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('retirado_en');
    }

    public function esOtro(): bool
    {
        return $this->documento_codigo === self::OTRO;
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
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /**
     * Los archivos subidos a este ítem (nunca se borran, F0.3).
     *
     * @return HasMany<Documento, $this>
     */
    public function archivos(): HasMany
    {
        return $this->hasMany(Documento::class, 'entrevista_documento_id')->orderBy('id');
    }
}
