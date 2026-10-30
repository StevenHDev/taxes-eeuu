<?php

namespace App\Models;

use App\Enums\FieldState;
use App\Enums\MetodoExtraccionDocumento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Services\DocumentoAlmacen;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $forma
 * @property int $tax_year
 * @property string|null $campo
 * @property string|null $documento_codigo
 * @property int|null $entrevista_id
 * @property int|null $persona_id
 * @property string $file_path
 * @property string $file_original_name
 * @property string $file_mime_type
 * @property int $file_size
 * @property string $formato
 * @property string|null $hash_contenido
 * @property bool $cifrado
 * @property MetodoExtraccionDocumento|null $metodo_extraccion
 * @property FieldState $estado_validacion
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id',
    'forma',
    'tax_year',
    'campo',
    'documento_codigo',
    'entrevista_id',
    'persona_id',
    'file_path',
    'file_original_name',
    'file_mime_type',
    'file_size',
    'formato',
    'hash_contenido',
    'cifrado',
    'metodo_extraccion',
    'estado_validacion',
])]
class Documento extends Model
{
    // Reemplazar o quitar un documento lo archiva, nunca lo borra: el IRS
    // exige conservarlo 3 años (F0.3 del documento madre).
    use Prunable, SoftDeletes;

    /**
     * Disco de Storage:: donde viven los archivos reales. Se resuelve en
     * tiempo de ejecución (no es una constante fija) porque en producción el
     * bucket S3 todavía no está configurado (2026-09-21): mientras
     * AWS_BUCKET no tenga valor se usa 'local' + un volumen Docker
     * compartido entre los contenedores frontend y worker como solución
     * puente, y apenas se cargue el bucket esto pasa a 's3' sin otro deploy
     * — basta con poner las credenciales en Dokploy y reiniciar. Se llegó a
     * esto porque el disco 'local' original tampoco sobrevivía un reinicio
     * del contenedor del worker (`storage/app` sin volumen persistente
     * montado, `Mounts: []` en `docker inspect`) — un reinicio a mitad de
     * conversación con un cliente real (3213445027) borró dos documentos ya
     * subidos, sin ningún error visible (storeAs() nunca falla ahí: escribe
     * bien en el contenedor que procesa la subida, el problema es que ese
     * contenedor es efímero). Todo lo que lee/escribe/borra un documento de
     * cliente pasa por `DocumentoAlmacen` (que usa este método y además
     * cifra), nunca por un `Storage::disk('local')` o `Storage::disk('s3')`
     * sueltos — evita que un punto se quede apuntando al disco equivocado o
     * lea el archivo sin descifrar.
     *
     * Nota para cuando se cargue el bucket: los documentos guardados
     * mientras este método devolvía 'local' NO se migran solos a S3 — sus
     * filas en `documentos` van a seguir apuntando a rutas que solo existen
     * en el disco local. Si hace falta preservarlos, hay que copiarlos a S3
     * a mano antes de (o al) cargar las credenciales.
     */
    public static function disco(): string
    {
        return filled(config('filesystems.disks.s3.bucket')) ? 's3' : 'local';
    }

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
            'cifrado' => 'boolean',
        ];
    }

    /**
     * Documentos archivados hace más de 3 años. Preparado pero sin programar
     * en el scheduler hasta que se confirme el plazo (L12).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subYears(3));
    }

    protected function pruning(): void
    {
        app(DocumentoAlmacen::class)->borrar($this->file_path);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La entrevista que lo pidió (F2.6); nulo en los documentos del
     * formulario actual, que usan `forma` y `campo`.
     *
     * @return BelongsTo<Entrevista, $this>
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class);
    }

    /**
     * @return BelongsTo<EntrevistaPersona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(EntrevistaPersona::class, 'persona_id')->withTrashed();
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
