<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Activo/inactivo de una tool para una fase puntual — leído vía
 * App\Support\AgenteToolEstados. `fase` no se castea a
 * App\Enums\FaseConversacion por la misma razón que App\Models\AgentePrompt:
 * queda como uno de sus values, para poder consultar/agrupar por string
 * directo.
 *
 * @property int $id
 * @property string $fase
 * @property string $tool_name
 * @property bool $activo
 */
#[Fillable(['fase', 'tool_name', 'activo'])]
class AgenteToolEstado extends Model
{
    protected $table = 'agente_tool_estados';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
