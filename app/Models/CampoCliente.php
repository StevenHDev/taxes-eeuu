<?php

namespace App\Models;

use App\Enums\EventSource;
use App\Enums\FieldKind;
use App\Enums\FieldMode;
use App\Enums\FieldState;
use App\Support\TaxFieldCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $forma
 * @property string $campo
 * @property FieldKind $tipo_campo
 * @property FieldMode $modo
 * @property mixed $valor_texto
 * @property int|null $documento_id
 * @property FieldState $estado
 * @property EventSource $source
 * @property int|null $actualizado_por
 * @property-read mixed $valor
 */
#[Fillable([
    'user_id',
    'forma',
    'campo',
    'tipo_campo',
    'modo',
    'valor_texto',
    'documento_id',
    'estado',
    'source',
    'actualizado_por',
])]
class CampoCliente extends Model
{
    /**
     * Convención de pluralización de Eloquent daría "campo_clientes" — el nombre
     * real de la tabla sigue el orden del dominio ("campos por cliente").
     */
    protected $table = 'campos_cliente';

    /**
     * El valor crudo nunca se serializa directamente — se expone solo a través
     * del accessor `valor`, que enmascara los campos sensibles (ver `esSensible()`).
     * Revelar el valor real de un campo sensible requiere el endpoint dedicado
     * de "reveal" (con reconfirmación de contraseña y auditoría).
     */
    protected $hidden = ['valor_texto'];

    protected $appends = ['valor'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_campo' => FieldKind::class,
            'modo' => FieldMode::class,
            // Cifrado siempre, sea o no un campo sensible: evita acoplar la
            // decisión de cifrar al orden de asignación de atributos, y sigue
            // funcionando ante cualquier vía de escritura (ver plan de diseño).
            'valor_texto' => 'encrypted:array',
            'estado' => FieldState::class,
            'source' => EventSource::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Documento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /**
     * @return Builder<HistorialCambio>
     */
    public function historial(): Builder
    {
        return HistorialCambio::query()
            ->where('user_id', $this->user_id)
            ->where('forma', $this->forma)
            ->where('campo', $this->campo)
            ->latest('created_at');
    }

    public function esSensible(): bool
    {
        return TaxFieldCatalog::isSensible($this->campo);
    }

    /**
     * @return Attribute<mixed, never>
     */
    protected function valor(): Attribute
    {
        return Attribute::get(fn (): mixed => $this->maskedValue());
    }

    /**
     * Representación enmascarada para mostrar en el panel sin revelar el valor real.
     */
    public function maskedValue(): mixed
    {
        if (! $this->esSensible() || $this->valor_texto === null) {
            return $this->valor_texto;
        }

        return $this->maskRecursively($this->valor_texto);
    }

    private function maskRecursively(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->maskRecursively($item), $value);
        }

        if (is_string($value) && strlen($value) > 4) {
            return str_repeat('*', strlen($value) - 4).substr($value, -4);
        }

        return is_string($value) ? str_repeat('*', strlen($value)) : $value;
    }
}
