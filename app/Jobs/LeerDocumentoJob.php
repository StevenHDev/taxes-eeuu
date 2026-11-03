<?php

namespace App\Jobs;

use App\Enums\Lectura\EstadoLectura;
use App\Models\Documento;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoLectura;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\EsquemaLectura;
use App\Services\Lectura\LectorDocumentos;
use App\Services\Lectura\Reglas\MotorReglas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Lee un archivo subido (F4.4): crea la lectura, la hace el lector, guarda los
 * datos, deja el ítem en `leido` y corre las reglas (MotorReglas). Cada intento es una lectura; si lanza una
 * excepción queda `fallida` y la cola reintenta (3 intentos, con espera). Si
 * se acaban, el ítem pasa a `no_se_pudo_leer`: nunca queda `subido` sin datos
 * ni aviso (documento madre 6.1.5).
 *
 * No lee lo que no se lee solo (la bandera apagada, o un documento sin
 * casillas): ahí el preparador escribe los datos en el visor y el ítem sigue
 * `subido`. Los errores no llevan datos del cliente.
 */
class LeerDocumentoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $documentoId)
    {
        $this->afterCommit();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(LectorDocumentos $lector, DatosDocumento $datos, ChecklistDocumentos $checklist, EsquemaLectura $esquemas, MotorReglas $reglas): void
    {
        $documento = Documento::query()->find($this->documentoId);
        $item = $documento?->entrevista_documento_id === null ? null : EntrevistaDocumento::query()->with('entrevista')->find($documento->entrevista_documento_id);

        if ($documento === null || $item === null || $item->retirado_en !== null || $item->entrevista === null) {
            return;
        }

        $anio = $item->entrevista->tax_year;

        if (! config('tax.lectura_automatica') || ! array_key_exists($item->documento_codigo, $esquemas->documentosLeibles($anio))) {
            return;
        }

        $lectura = DocumentoLectura::query()->create([
            'documento_id' => $documento->id,
            'entrevista_documento_id' => $item->id,
            'lector' => 'openai',
            'estado' => EstadoLectura::EnCola,
        ]);

        try {
            $datos->guardarLectura($lectura, $lector->leer($documento, $lectura, $item->documento_codigo, $anio));
        } catch (Throwable $error) {
            $datos->fallar($lectura, $error);

            throw $error;
        }

        $checklist->leido($item);
        $reglas->correr($item->entrevista);
    }

    public function failed(?Throwable $error): void
    {
        $documento = Documento::query()->find($this->documentoId);
        $item = $documento?->entrevista_documento_id === null ? null : EntrevistaDocumento::query()->find($documento->entrevista_documento_id);

        if ($item !== null) {
            app(ChecklistDocumentos::class)->noSePudoLeer($item);
        }
    }
}
