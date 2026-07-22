<?php

namespace App\Models;

use App\Enums\FieldState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $user_id
 * @property string $forma
 * @property string $campo
 * @property string $file_path
 * @property string $file_original_name
 * @property string $file_mime_type
 * @property int $file_size
 * @property string $formato
 * @property FieldState $estado_validacion
 */
#[Fillable([
    'user_id',
    'forma',
    'campo',
    'file_path',
    'file_original_name',
    'file_mime_type',
    'file_size',
    'formato',
    'estado_validacion',
])]
class Documento extends Model
{
    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado_validacion' => FieldState::class,
            'file_size' => 'integer',
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
     * URL de descarga firmada y temporal (10 minutos) — nunca una URL pública permanente.
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute('documentos.show', now()->addMinutes(10), ['documento' => $this->id]);
    }
}
