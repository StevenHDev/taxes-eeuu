<?php

namespace App\Models\Lectura;

use App\Enums\Lectura\ClaseDetectada;
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
 * @property ClaseDetectada|null $clase_detectada
 * @property Carbon|null $periodo_desde
 * @property Carbon|null $periodo_hasta
 * @property string|null $cuenta_ultimos4
 * @property string|null $total_depositos
 * @property string|null $total_retiros
 * @property string|null $saldo_inicial
 * @property string|null $saldo_final
 * @property array{columnas_no_reconocidas?: list<string>, filas_sin_monto?: int, formulas_sin_valor?: int, filas_sin_fecha?: int, hojas_sin_leer?: int, formato_no_reconocido?: bool}|null $observaciones
 * @property string|null $error
 * @property Carbon|null $terminada_en
 */
#[Fillable(['documento_id', 'entrevista_documento_id', 'lector', 'modelo', 'estado', 'tipo_detectado', 'anio_detectado', 'paginas', 'error', 'terminada_en', 'clase_detectada', 'periodo_desde', 'periodo_hasta', 'cuenta_ultimos4', 'total_depositos', 'total_retiros', 'saldo_inicial', 'saldo_final', 'observaciones'])]
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
            'clase_detectada' => ClaseDetectada::class,
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'cuenta_ultimos4' => 'encrypted',
            'total_depositos' => 'encrypted',
            'total_retiros' => 'encrypted',
            'saldo_inicial' => 'encrypted',
            'saldo_final' => 'encrypted',
            'observaciones' => 'array',
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

    /**
     * @return HasMany<DocumentoRenglon, $this>
     */
    public function renglones(): HasMany
    {
        return $this->hasMany(DocumentoRenglon::class, 'lectura_id')->orderBy('numero');
    }
}
