<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega de documentos vía URL firmada y temporal (sección 6.5: "nunca URLs
 * públicas permanentes"). La ruta que apunta aquí lleva middleware `signed`,
 * así que la propia firma —generada por ClienteController/Api\ClienteController
 * al listar documentos— actúa como autorización para esa ventana de tiempo.
 *
 * Con `?disposition=inline` el archivo se sirve para visualizarse en el navegador
 * (previsualización a pantalla completa); en cualquier otro caso se descarga.
 */
class DocumentoController extends Controller
{
    public function show(Request $request, Documento $documento): StreamedResponse
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        // El registro puede existir en BD aunque el archivo físico ya no esté
        // (ej. datos sembrados sin archivo real). Sin esta guarda, Flysystem
        // lanza UnableToRetrieveMetadata y devuelve un 500.
        abort_unless($disk->exists($documento->file_path), 404, 'El archivo ya no está disponible.');

        $disposition = $request->query('disposition') === 'inline' ? 'inline' : 'attachment';

        return $disk->response(
            $documento->file_path,
            $documento->file_original_name,
            ['Content-Type' => $documento->file_mime_type],
            $disposition,
        );
    }
}
