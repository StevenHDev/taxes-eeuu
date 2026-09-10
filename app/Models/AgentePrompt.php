<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bloque de prompt de una fase del agente conversacional, versionado — leído
 * vía App\Support\AgentePromptVigente. `fase` no se castea a
 * App\Enums\FaseConversacion para poder hacer pluck('contenido', 'fase')
 * directo en AgentePromptVigente; sigue siendo uno de sus values.
 *
 * @property int $id
 * @property int $version
 * @property string $fase
 * @property string $contenido
 * @property Carbon|null $publicada_en
 */
#[Fillable(['version', 'fase', 'contenido', 'publicada_en'])]
class AgentePrompt extends Model
{
    protected $table = 'agente_prompts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publicada_en' => 'datetime',
        ];
    }
}
