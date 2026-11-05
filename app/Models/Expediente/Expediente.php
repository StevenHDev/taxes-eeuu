<?php

namespace App\Models\Expediente;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Expediente\EstadoExpediente;
use App\Models\Entrevista\Entrevista;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El expediente de un cliente en un año fiscal: junta el estado con fecha, las
 * decisiones del preparador y los datos de presentación. El estado solo lo
 * cambian App\Services\Expediente\EstadosExpediente y RevisionExpediente.
 * Los motivos son texto del preparador y nunca van a la bitácora general.
 *
 * @property int $id
 * @property int $user_id
 * @property int $tax_year
 * @property int|null $entrevista_id
 * @property EstadoExpediente $estado
 * @property Carbon $estado_desde
 * @property EstadoCivilIrs|null $estado_civil_irs
 * @property string|null $estado_civil_motivo
 * @property int|null $estado_civil_por
 * @property Carbon|null $estado_civil_en
 * @property list<string>|null $creditos_8867
 * @property int|null $creditos_8867_por
 * @property Carbon|null $creditos_8867_en
 * @property Carbon|null $presentado_en
 * @property Carbon|null $cambios_tras_presentar_en
 * @property list<string>|null $perfil
 * @property Carbon|null $vence_en
 * @property-read User $user
 * @property-read Entrevista|null $entrevista
 * @property-read Collection<int, ExpedienteEvento> $eventos
 * @property-read Collection<int, ExpedientePersona> $personas
 * @property-read Collection<int, ExpedienteRevisionPunto> $puntos
 */
#[Fillable([
    'user_id', 'tax_year', 'entrevista_id', 'estado', 'estado_desde',
    'estado_civil_irs', 'estado_civil_motivo', 'estado_civil_por', 'estado_civil_en',
    'creditos_8867', 'creditos_8867_por', 'creditos_8867_en',
    'presentado_en', 'cambios_tras_presentar_en', 'perfil', 'vence_en',
])]
class Expediente extends Model
{
    protected $table = 'expedientes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'estado' => EstadoExpediente::class,
            'estado_desde' => 'datetime',
            'estado_civil_irs' => EstadoCivilIrs::class,
            'estado_civil_en' => 'datetime',
            'creditos_8867' => 'array',
            'creditos_8867_en' => 'datetime',
            'presentado_en' => 'date',
            'cambios_tras_presentar_en' => 'datetime',
            'perfil' => 'array',
            'vence_en' => 'date',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDelAnio(Builder $query, int $taxYear): void
    {
        $query->where('tax_year', $taxYear);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Entrevista, $this>
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class);
    }

    /**
     * @return HasMany<ExpedienteEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(ExpedienteEvento::class)->orderBy('id');
    }

    /**
     * @return HasMany<ExpedientePersona, $this>
     */
    public function personas(): HasMany
    {
        return $this->hasMany(ExpedientePersona::class);
    }

    /**
     * @return HasMany<ExpedienteRevisionPunto, $this>
     */
    public function puntos(): HasMany
    {
        return $this->hasMany(ExpedienteRevisionPunto::class);
    }

    /**
     * Quien decidió el estado civil para el IRS.
     *
     * @return BelongsTo<User, $this>
     */
    public function decisorEstadoCivil(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estado_civil_por');
    }
}
