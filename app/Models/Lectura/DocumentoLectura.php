<?php

namespace App\Models\Lectura;

use App\Enums\Lectura\EstadoLectura;
use App\Models\Documento;
use App\Models\Entrevista\EntrevistaDocumento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un intento de leer un archivo (F4.2). Releer crea otro; los datos que
 * cuentan son los de la última lectura terminada.
 *
 * @property int $id
 * @property int $documento_id
 * @property int|null $entrevista_documento_id
 * @property string $lector
 * @property string|null $modelo
 * @property EstadoLectura $estado
 * @property string|null $tipo_detectado
 * @property int|null $anio_detectado
 * @property int $paginas
 * @property string|null $error
 * @property Carbon|null $terminada_en
 */
#[Fillable(['documento_id', 'entrevista_documento_id', 'lector', 'modelo', 'estado', 'tipo_detectado', 'anio_detectado', 'paginas', 'error', 'terminada_en'])]
class DocumentoLectura extends Model
{
    protected $table = 'documento_lecturas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoLectura::class,
            'anio_detectado' => 'integer',
            'paginas' => 'integer',
            'terminada_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Documento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * @return BelongsTo<EntrevistaDocumento, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(EntrevistaDocumento::class, 'entrevista_documento_id');
    }

    /**
     * @return HasMany<DocumentoDato, $this>
     */
    public function datos(): HasMany
    {
        return $this->hasMany(DocumentoDato::class, 'lectura_id');
    }
}
