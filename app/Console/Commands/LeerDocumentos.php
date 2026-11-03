<?php

namespace App\Console\Commands;

use App\Enums\Lectura\EstadoLectura;
use App\Jobs\LeerDocumentoJob;
use App\Models\Documento;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Services\Lectura\EsquemaLectura;
use Illuminate\Console\Command;

/**
 * Encola la lectura (F4.4) de los archivos vigentes que todavía no tienen una
 * lectura terminada: los subidos antes de la fase 4, o los que fallaron. Solo
 * los documentos que se leen solo, y solo con la lectura automática prendida.
 * Solo muestra cantidades: nunca nombres de archivo ni valores.
 */
class LeerDocumentos extends Command
{
    protected $signature = 'documentos:leer {--entrevista= : Solo los archivos de esta entrevista} {--solo-prueba : Solo clientes de prueba} {--dry-run : Cuenta lo que se leería, sin encolar}';

    protected $description = 'Encola la lectura de los archivos subidos que todavía no se han leído';

    public function handle(EsquemaLectura $esquemas): int
    {
        if (! config('tax.lectura_automatica')) {
            $this->warn('La lectura automática está apagada (LECTURA_AUTOMATICA): no se encola nada.');

            return self::SUCCESS;
        }

        $leibles = [];
        $encontrados = 0;

        Documento::query()
            ->whereNotNull('entrevista_documento_id')
            ->when($this->option('entrevista'), fn ($q, $id) => $q->where('entrevista_id', (int) $id))
            ->when($this->option('solo-prueba'), fn ($q) => $q->whereHas('entrevista.user', fn ($u) => $u->where('es_prueba', true)))
            ->whereDoesntHave('lecturas', fn ($q) => $q->where('estado', EstadoLectura::Leida))
            ->with('entrevista')
            ->orderBy('id')
            ->chunkById(100, function ($lote) use ($esquemas, &$leibles, &$encontrados) {
                foreach ($lote as $documento) {
                    $item = $documento->entrevista_documento_id === null ? null : EntrevistaDocumento::query()->find($documento->entrevista_documento_id);

                    if ($item === null || $item->retirado_en !== null || $documento->entrevista === null) {
                        continue;
                    }

                    $anio = $documento->entrevista->tax_year;
                    $leibles[$anio] ??= $esquemas->documentosLeibles($anio);

                    if (! array_key_exists($item->documento_codigo, $leibles[$anio])) {
                        continue;
                    }

                    $encontrados++;

                    if (! $this->option('dry-run')) {
                        LeerDocumentoJob::dispatch($documento->id);
                    }
                }
            });

        $this->info(($this->option('dry-run') ? 'Se leerían' : 'Encolados')." {$encontrados} archivos.");

        return self::SUCCESS;
    }
}
