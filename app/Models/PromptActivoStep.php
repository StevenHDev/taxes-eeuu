<?php

namespace App\Models;

use App\Enums\TipoPromptActivoStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un paso de la lista ACTIVOS de "CAMPOS TRANSVERSALES: ACTIVOS VS. PASIVOS"
 * — ver la migración `create_prompt_activo_steps_table` y
 * App\Services\WhatsappAgent\ActivosPromptComposer, que los compila a texto.
 *
 * @property int $id
 * @property int $orden
 * @property TipoPromptActivoStep $tipo
 * @property string|null $campo
 * @property string|null $condicion
 * @property string|null $nota_si_no_aplica
 * @property string|null $nota
 * @property string|null $pregunta
 * @property string|null $campo_si
 * @property string|null $campo_no
 * @property string|null $etiqueta
 */
#[Fillable([
    'orden',
    'tipo',
    'campo',
    'condicion',
    'nota_si_no_aplica',
    'nota',
    'pregunta',
    'campo_si',
    'campo_no',
    'etiqueta',
])]
class PromptActivoStep extends Model
{
    protected $table = 'prompt_activo_steps';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPromptActivoStep::class,
        ];
    }
}
