<?php

namespace App\Models\Consentimiento;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * La firma de un consentimiento por un cliente para un año (F2.4): qué
 * texto y versión vio, el nombre que escribió como firma, cuándo, desde qué
 * IP y navegador (8.3). Solo se inserta: no se edita ni se borra (se borra
 * solo con la cuenta de un usuario de prueba, F0.3).
 *
 * @property int $id
 * @property int $user_id
 * @property int $tax_year
 * @property int $consentimiento_texto_id
 * @property string $nombre_escrito
 * @property Carbon $aceptado_en
 * @property string|null $ip
 * @property string|null $user_agent
 * @property-read ConsentimientoTexto $texto
 */
#[Fillable(['user_id', 'tax_year', 'consentimiento_texto_id', 'nombre_escrito', 'aceptado_en', 'ip', 'user_agent'])]
class Consentimiento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'consentimientos';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Un consentimiento firmado no se modifica.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'aceptado_en' => 'datetime',
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
     * @return BelongsTo<ConsentimientoTexto, $this>
     */
    public function texto(): BelongsTo
    {
        return $this->belongsTo(ConsentimientoTexto::class, 'consentimiento_texto_id');
    }
}
