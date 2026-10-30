<?php

namespace App\Models\Consentimiento;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una versión del texto de un consentimiento (F2.4). Se carga desde
 * database/data/consentimientos con `consentimientos:cargar`; una versión
 * que ya tiene firmas no cambia nunca (un texto nuevo es una versión nueva).
 *
 * @property int $id
 * @property string $codigo
 * @property int $version
 * @property string $titulo_es
 * @property string|null $titulo_en
 * @property string $texto_es
 * @property string|null $texto_en
 * @property bool $provisional
 * @property Carbon $vigente_desde
 */
#[Fillable(['codigo', 'version', 'titulo_es', 'titulo_en', 'texto_es', 'texto_en', 'provisional', 'vigente_desde'])]
class ConsentimientoTexto extends Model
{
    public const USO_7216 = 'uso_7216';

    protected $table = 'consentimiento_textos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'provisional' => 'boolean',
            'vigente_desde' => 'date',
        ];
    }

    /**
     * @return HasMany<Consentimiento, $this>
     */
    public function firmas(): HasMany
    {
        return $this->hasMany(Consentimiento::class, 'consentimiento_texto_id');
    }
}
