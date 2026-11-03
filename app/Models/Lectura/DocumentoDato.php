<?php

namespace App\Models\Lectura;

use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Entrevista\ValorCifrado;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un dato leído de una casilla del catálogo (documento madre, 6.1.3): el
 * valor, la confianza y la evidencia (página, recuadro y el texto que se
 * leyó al lado). El valor y la evidencia van cifrados. `valor_leido` no
 * cambia nunca; las correcciones del preparador cambian `valor` y quedan en
 * DocumentoDatoCambio. Se escribe solo con App\Services\Lectura\DatosDocumento.
 *
 * @property int $id
 * @property int $lectura_id
 * @property int|null $entrevista_documento_id
 * @property string $casilla
 * @property string|int|float|list<string>|null $valor
 * @property string|int|float|list<string>|null $valor_leido
 * @property float|null $confianza
 * @property MotivoConfianza|null $motivo_confianza
 * @property Legibilidad|null $legibilidad
 * @property int|null $pagina
 * @property array{x: float, y: float, ancho: float, alto: float}|null $recuadro
 * @property string|null $evidencia
 * @property OrigenDato $origen
 * @property int|null $corregido_por
 * @property Carbon|null $corregido_en
 * @property string|null $motivo
 */
#[Fillable(['lectura_id', 'entrevista_documento_id', 'casilla', 'valor', 'valor_leido', 'confianza', 'motivo_confianza', 'legibilidad', 'pagina', 'recuadro', 'evidencia', 'origen', 'corregido_por', 'corregido_en', 'motivo'])]
class DocumentoDato extends Model
{
    protected $table = 'documento_datos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => ValorCifrado::class,
            'valor_leido' => ValorCifrado::class,
            'confianza' => 'float',
            'motivo_confianza' => MotivoConfianza::class,
            'legibilidad' => Legibilidad::class,
            'pagina' => 'integer',
            'recuadro' => 'array',
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
     * @return HasMany<DocumentoDatoCambio, $this>
     */
    public function cambios(): HasMany
    {
        return $this->hasMany(DocumentoDatoCambio::class, 'dato_id');
    }
}
