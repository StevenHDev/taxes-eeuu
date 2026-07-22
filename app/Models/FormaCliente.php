<?php

namespace App\Models;

use App\Enums\FormState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $forma
 * @property FormState $estado
 * @property Carbon|null $revisado_en
 * @property int|null $revisado_por
 */
#[Fillable(['user_id', 'forma', 'estado', 'revisado_en', 'revisado_por'])]
class FormaCliente extends Model
{
    /**
     * Convención de pluralización de Eloquent daría "forma_clientes" — el nombre
     * real de la tabla sigue el orden del dominio ("formas por cliente").
     */
    protected $table = 'formas_cliente';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => FormState::class,
            'revisado_en' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function marcarRevisado(User $por): void
    {
        $this->update([
            'revisado_en' => Carbon::now(),
            'revisado_por' => $por->id,
        ]);
    }
}
