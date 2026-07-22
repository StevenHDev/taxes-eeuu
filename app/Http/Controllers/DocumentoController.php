<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de documentos vía URL firmada y temporal (sección 6.5: "nunca URLs
 * públicas permanentes"). La ruta que apunta aquí lleva middleware `signed`,
 * así que la propia firma —generada por ClienteController/Api\ClienteController
 * al listar documentos— actúa como autorización para esa ventana de tiempo.
 */
class DocumentoController extends Controller
{
    public function show(Documento $documento): StreamedResponse
    {
        return Storage::disk('local')->download($documento->file_path, $documento->file_original_name);
    }
}
