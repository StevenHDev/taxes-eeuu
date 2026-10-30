<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\User;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Exporta el paquete consolidado de un cliente (documentos + datos) en un ZIP,
 * para pasarlo al software de preparación de impuestos (sección 6.1).
 * El PDF consolidado queda fuera de este alcance (ver backlog).
 */
class ClienteExportService
{
    public function __construct(private readonly DocumentoAlmacen $almacen) {}

    public function exportarZip(User $cliente, int $taxYear): string
    {
        $cliente->load(['camposCliente' => fn ($query) => $query->where('tax_year', $taxYear)->with('documento')]);

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
            $contents = $this->almacen->leer($documento);

            if (is_string($contents)) {
                $zip->addFromString(
                    "documentos/{$documento->forma}_{$documento->campo}_{$documento->file_original_name}",
                    $contents,
                );
            }
        }

        // F2.6: los archivos que se suben en la entrevista no cuelgan de un
        // campo del formulario actual, sino de un documento del catálogo.
        Documento::query()
            ->where('user_id', $cliente->id)
            ->whereNotNull('documento_codigo')
            ->orderBy('id')
            ->each(function (Documento $documento) use ($zip) {
                $contents = $this->almacen->leer($documento);

                if (is_string($contents)) {
                    $zip->addFromString("documentos/{$documento->documento_codigo}_{$documento->id}_{$documento->file_original_name}", $contents);
                }
            });

        $zip->close();

        return $zipPath;
    }
}
