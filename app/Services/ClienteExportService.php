<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Exporta el paquete consolidado de un cliente (documentos + datos) en un ZIP,
 * para pasarlo al software de preparación de impuestos (sección 6.1).
 * El PDF consolidado queda fuera de este alcance (ver backlog).
 */
class ClienteExportService
{
    public function exportarZip(User $cliente): string
    {
        $cliente->load(['camposCliente.documento']);

        $zipPath = storage_path('app/private/exports/'.Str::uuid().'.zip');

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), recursive: true);
        }

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $campos = $cliente->camposCliente->map(fn ($c) => [
            'forma' => $c->forma,
            'campo' => $c->campo,
            'estado' => $c->estado,
            'valor' => $c->valor,
        ]);

        $zip->addFromString('campos.json', json_encode($campos, JSON_PRETTY_PRINT) ?: '[]');

        foreach ($cliente->camposCliente as $campoCliente) {
            if (! $campoCliente->documento) {
                continue;
            }

            $documento = $campoCliente->documento;
            $contents = Storage::disk('local')->get($documento->file_path);

            if (is_string($contents)) {
                $zip->addFromString(
                    "documentos/{$documento->forma}_{$documento->campo}_{$documento->file_original_name}",
                    $contents,
                );
            }
        }

        $zip->close();

        return $zipPath;
    }
}
