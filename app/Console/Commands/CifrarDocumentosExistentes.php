<?php

namespace App\Console\Commands;

use App\Models\Documento;
use App\Services\DocumentoAlmacen;
use Illuminate\Console\Command;

/**
 * Cifra los archivos de documentos subidos antes de F0.2 (`cifrado = false`),
 * incluidos los archivados. Va de a uno y se puede correr dos veces: los ya
 * cifrados no se tocan. Los que no tienen archivo en el disco se listan y
 * se dejan como están.
 */
class CifrarDocumentosExistentes extends Command
{
    protected $signature = 'documentos:cifrar-existentes
        {--dry-run : Solo cuenta los documentos pendientes, sin escribir}';

    protected $description = 'Cifra los archivos de documentos guardados antes de F0.2';

    public function handle(DocumentoAlmacen $almacen): int
    {
        $pendientes = Documento::withTrashed()->where('cifrado', false);

        $this->info(sprintf('%d documento(s) sin cifrar.', (clone $pendientes)->count()));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $cifrados = 0;
        $sinArchivo = [];

        // chunkById y no chunk: cada vuelta cambia `cifrado`, que es el filtro.
        $pendientes->chunkById(50, function ($documentos) use ($almacen, &$cifrados, &$sinArchivo) {
            foreach ($documentos as $documento) {
                if ($almacen->cifrarExistente($documento)) {
                    $cifrados++;
                } else {
                    $sinArchivo[] = $documento->id;
                }
            }
        });

        $this->info("{$cifrados} documento(s) cifrado(s).");

        if ($sinArchivo !== []) {
            $this->warn(sprintf('%d sin archivo en el disco (ids: %s); quedan sin cifrar.', count($sinArchivo), implode(', ', $sinArchivo)));
        }

        return self::SUCCESS;
    }
}
