<?php

namespace App\Models\Expediente;

use App\Models\Entrevista\EntrevistaPersona;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un monto anotado por el preparador porque el cliente no tiene ningún
 * documento (F6 6.5, último recurso). Se escribe solo con
 * App\Services\Expediente\DatosSinDocumento. El valor va cifrado y, como el
 * motivo, nunca sale en logs, bitácora ni excepciones.
 *
 * @property int $id
 * @property int $expediente_id
 * @property string $clave
 * @property int|null $persona_id
 * @property string $valor
 * @property string $motivo
 * @property string $fuente
 * @property Carbon|null $fuente_fecha
 * @property string|null $fuente_canal
 * @property int|null $anotado_por
 * @property Carbon|null $reemplazado_en
 * @property Carbon|null $retirado_en
 * @property int|null $retirado_por
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Expediente $expediente
 * @property-read EntrevistaPersona|null $persona
 * @property-read User|null $anotador
 */
#[Fillable(['expediente_id', 'clave', 'persona_id', 'valor', 'motivo', 'fuente', 'fuente_fecha', 'fuente_canal', 'anotado_por', 'reemplazado_en', 'retirado_en', 'retirado_por'])]
class ExpedienteDatoSinDocumento extends Model
{
    /** El cliente lo dijo (con la fecha y el canal) u otra fuente que explica el motivo. */
    public const FUENTES = ['cliente_dijo', 'otro'];

    /** Por dónde lo dijo el cliente. */
    public const CANALES = ['llamada', 'whatsapp', 'presencial', 'correo', 'portal'];

    protected $table = 'expediente_datos_sin_documento';

    /**
     * El cifrado no se vuelca al serializar: nunca va a una respuesta por accidente.
     *
     * @var list<string>
     */
    protected $hidden = ['valor', 'motivo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'encrypted',
            'fuente_fecha' => 'date',
            'reemplazado_en' => 'datetime',
            'retirado_en' => 'datetime',
        ];
    }

    /**
     * Lo que todavía cuenta: ni reemplazado por el documento ni retirado.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('reemplazado_en')->whereNull('retirado_en');
    }

    public function vigente(): bool
    {
        return $this->reemplazado_en === null && $this->retirado_en === null;
    }

    /** El código del documento de la clave (`comprobantes-pagos`). */
    public function documentoCodigo(): string
    {
        return explode(':', $this->clave, 2)[0];
    }

    /**
     * @return BelongsTo<Expediente, $this>
     */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
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
    public function anotador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anotado_por');
    }

    /**
     * @return HasMany<ExpedienteDatoSinDocumentoCambio, $this>
     */
    public function cambios(): HasMany
    {
        return $this->hasMany(ExpedienteDatoSinDocumentoCambio::class, 'dato_id');
    }
}
