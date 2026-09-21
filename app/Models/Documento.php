<?php

namespace App\Models;

use App\Enums\FieldState;
use App\Enums\MetodoExtraccionDocumento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $user_id
 * @property string $forma
 * @property int $tax_year
 * @property string $campo
 * @property string $file_path
 * @property string $file_original_name
 * @property string $file_mime_type
 * @property int $file_size
 * @property string $formato
 * @property string|null $hash_contenido
 * @property MetodoExtraccionDocumento|null $metodo_extraccion
 * @property FieldState $estado_validacion
 */
#[Fillable([
    'user_id',
    'forma',
    'tax_year',
    'campo',
    'file_path',
    'file_original_name',
    'file_mime_type',
    'file_size',
    'formato',
    'hash_contenido',
    'metodo_extraccion',
    'estado_validacion',
])]
class Documento extends Model
{
    /**
     * Disco de Storage:: donde viven los archivos reales — S3 desde que se
     * encontró en producción (2026-09-21, conversación real con
     * 3213445027) que el disco 'local' anterior no sobrevive un reinicio
     * del contenedor del worker: `storage/app` no tenía ningún volumen
     * persistente montado (Mounts: [] en `docker inspect`), así que un
     * reinicio a mitad de conversación borró los dos documentos que el
     * cliente ya había subido — la fila en `documentos` quedó apuntando a
     * una ruta que ya no existía en ningún lado, sin ningún error visible
     * (el guardado en sí nunca falla: storeAs() escribe correctamente en el
     * contenedor que procesa la subida, el problema es que ese contenedor
     * es efímero). Todo lo que lee/escribe/borra un documento de cliente
     * debe pasar por esta constante, nunca por un `Storage::disk('local')`
     * suelto — evita que un punto se quede en el disco viejo por olvido.
     */
    public const DISK = 's3';

    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado_validacion' => FieldState::class,
            'metodo_extraccion' => MetodoExtraccionDocumento::class,
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

    /**
     * URL firmada y temporal para visualizar el archivo en el navegador (inline),
     * usada por la previsualización a pantalla completa. `disposition` viaja firmado.
     */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute('documentos.show', now()->addMinutes(10), [
            'documento' => $this->id,
            'disposition' => 'inline',
        ]);
    }
}
