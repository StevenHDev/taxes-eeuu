<?php

namespace App\Models;

use App\Enums\EstadoBaseConocimiento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de la base de conocimiento del agente — ver
 * App\Services\BaseConocimientoService. `ruta_pdf` queda oculta porque el
 * archivo original solo se sirve, si acaso, vía una ruta firmada (mismo
 * criterio que Documento::file_path); `contenido_markdown` sí se expone, es
 * lo único que la tool consultar_base_conocimiento necesita leer.
 *
 * @property int $id
 * @property string $nombre_original
 * @property string $ruta_pdf
 * @property string|null $contenido_markdown
 * @property int $tamano
 * @property EstadoBaseConocimiento $estado
 * @property string|null $error_mensaje
 * @property int|null $subido_por_user_id
 */
#[Fillable(['nombre_original', 'ruta_pdf', 'contenido_markdown', 'tamano', 'estado', 'error_mensaje', 'subido_por_user_id'])]
class BaseConocimientoDocumento extends Model
{
    protected $table = 'base_conocimiento_documentos';

    protected $hidden = ['ruta_pdf'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoBaseConocimiento::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }
}
