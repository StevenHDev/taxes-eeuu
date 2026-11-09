<?php

namespace App\Models\Lectura;

use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenCategoria;
use App\Enums\Lectura\OrigenDato;
use App\Enums\Lectura\SentidoRenglon;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un renglón de los registros del negocio (F6 6.3b): un movimiento de una
 * hoja o un monto de la plantilla, con su categoría (la línea del Schedule C)
 * y su confianza. La descripción y el monto van cifrados; el monto es un
 * decimal en texto, siempre positivo: la dirección la dice `sentido`. Se
 * escribe solo con App\Services\Lectura\DatosDocumento.
 *
 * @property int $id
 * @property int $lectura_id
 * @property int|null $entrevista_documento_id
 * @property int $numero
 * @property int|null $pagina
 * @property array{x: float, y: float, ancho: float, alto: float}|null $recuadro
 * @property Carbon|null $fecha
 * @property string|null $descripcion
 * @property string|null $monto
 * @property SentidoRenglon $sentido
 * @property string|null $categoria
 * @property OrigenCategoria|null $categoria_origen
 * @property float|null $confianza
 * @property MotivoConfianza|null $motivo_confianza
 * @property Legibilidad|null $legibilidad
 * @property string|null $evidencia
 * @property int|null $cubierto_por_item_id
 * @property OrigenDato $origen
 * @property int|null $corregido_por
 * @property Carbon|null $corregido_en
 * @property string|null $motivo
 */
#[Fillable(['lectura_id', 'entrevista_documento_id', 'numero', 'pagina', 'recuadro', 'fecha', 'descripcion', 'monto', 'sentido', 'categoria', 'categoria_origen', 'confianza', 'motivo_confianza', 'legibilidad', 'evidencia', 'cubierto_por_item_id', 'origen', 'corregido_por', 'corregido_en', 'motivo'])]
class DocumentoRenglon extends Model
{
    protected $table = 'documento_renglones';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'pagina' => 'integer',
            'recuadro' => 'array',
            'fecha' => 'date',
            'descripcion' => 'encrypted',
            'monto' => 'encrypted',
            'sentido' => SentidoRenglon::class,
            'categoria_origen' => OrigenCategoria::class,
            'confianza' => 'float',
            'motivo_confianza' => MotivoConfianza::class,
            'legibilidad' => Legibilidad::class,
            'evidencia' => 'encrypted',
            'origen' => OrigenDato::class,
            'corregido_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DocumentoLectura, $this>
     */
    public function lectura(): BelongsTo
    {
        return $this->belongsTo(DocumentoLectura::class, 'lectura_id');
    }

    /**
     * @return BelongsTo<EntrevistaDocumento, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(EntrevistaDocumento::class, 'entrevista_documento_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregido_por');
    }

    /**
     * @return HasMany<DocumentoRenglonCambio, $this>
     */
    public function cambios(): HasMany
    {
        return $this->hasMany(DocumentoRenglonCambio::class, 'renglon_id');
    }
}
