<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Services\DocumentoAlmacen;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega de documentos vía URL firmada y temporal (sección 6.5: "nunca URLs
 * públicas permanentes"). La ruta que apunta aquí lleva middleware `signed`,
 * así que la propia firma —generada por ClienteController/Api\ClienteController
 * al listar documentos— actúa como autorización para esa ventana de tiempo.
 *
 * Con `?disposition=inline` el archivo se sirve para visualizarse en el navegador
 * (previsualización a pantalla completa); en cualquier otro caso se descarga.
 * El archivo está cifrado en disco (F0.2): `DocumentoAlmacen` lo descifra.
 */
class DocumentoController extends Controller
{
    public function show(Request $request, Documento $documento, DocumentoAlmacen $almacen): Response
    {
        // El registro puede existir en BD aunque el archivo físico ya no esté
        // (ej. datos sembrados sin archivo real).
        abort_unless($almacen->existe($documento), 404, 'El archivo ya no está disponible.');

        $disposition = $request->query('disposition') === 'inline' ? 'inline' : 'attachment';

        return $almacen->responder($documento, $disposition);
    }
}
