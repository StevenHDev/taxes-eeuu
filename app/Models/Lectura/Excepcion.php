<?php

namespace App\Models\Lectura;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Lectura\EstadoExcepcion;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una regla que no se cumple (documento madre, 6.3). `clave` la hace única
 * por entrevista (regla + ítem + dato), así correr las reglas de nuevo
 * actualiza la misma excepción en vez de duplicarla. `detalle` nunca lleva
 * valores sensibles en claro.
 *
 * @property int $id
 * @property int $entrevista_id
 * @property int|null $entrevista_documento_id
 * @property int|null $documento_dato_id
 * @property string $regla_codigo
 * @property SeveridadRegla $severidad
 * @property string $clave
 * @property array<string, mixed>|null $detalle
 * @property EstadoExcepcion $estado
 * @property int|null $resuelta_por
 * @property Carbon|null $resuelta_en
 * @property string|null $nota
 */
#[Fillable(['entrevista_id', 'entrevista_documento_id', 'documento_dato_id', 'regla_codigo', 'severidad', 'clave', 'detalle', 'estado', 'resuelta_por', 'resuelta_en', 'nota'])]
class Excepcion extends Model
{
    protected $table = 'excepciones';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severidad' => SeveridadRegla::class,
            'detalle' => 'array',
            'estado' => EstadoExcepcion::class,
            'resuelta_en' => 'datetime',
        ];
    }

    /**
     * Las que el preparador todavía tiene que mirar.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAbiertas(Builder $query): void
    {
        $query->whereIn('estado', [EstadoExcepcion::Abierta, EstadoExcepcion::Consultada]);
    }

    /**
     * @return BelongsTo<Entrevista, $this>
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class);
    }

    /**
     * @return BelongsTo<EntrevistaDocumento, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(EntrevistaDocumento::class, 'entrevista_documento_id');
    }

    /**
     * @return BelongsTo<DocumentoDato, $this>
     */
    public function dato(): BelongsTo
    {
        return $this->belongsTo(DocumentoDato::class, 'documento_dato_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }
}
