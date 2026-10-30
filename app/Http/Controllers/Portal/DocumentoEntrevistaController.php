<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SubirDocumentoRequest;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaGuardador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Documentos que pide la entrevista (F2.6): la lista y la carga. Los
 * documentos se suben solo por el portal, nunca por WhatsApp.
 */
class DocumentoEntrevistaController extends Controller
{
    public function __construct(
        private readonly DocumentosEntrevista $documentos,
        private readonly PasosEntrevista $pasos,
        private readonly RespuestaGuardador $guardador,
    ) {}

    public function index(Request $request): Response
    {
        $entrevista = $this->guardador->entrevistaDe($request->user(), CanalRespuesta::Portal);
        $situacion = $this->pasos->situacion($entrevista);

        return Inertia::render('portal/entrevista/documentos', [
            'taxYear' => $entrevista->tax_year,
            'documentos' => $this->documentos->lista($entrevista, $situacion, str_starts_with(app()->getLocale(), 'en')),
            'entrevistaCompleta' => $situacion->completa(),
        ]);
    }

    public function store(SubirDocumentoRequest $request): RedirectResponse
    {
        $entrevista = $request->entrevista();
        $persona = $request->persona();
        $archivos = $request->file('archivos');

        DB::transaction(function () use ($entrevista, $request, $persona, $archivos) {
            foreach ($archivos as $archivo) {
                $this->documentos->subir($entrevista, $request->codigo(), $persona, $archivo);
            }
        });

        // Con __() y no trans_choice(): sin un lang/es.json, trans_choice()
        // cae al inglés aunque el cliente use español.
        $mensaje = count($archivos) === 1 ? __('Subiste 1 archivo.') : __('Subiste :n archivos.', ['n' => count($archivos)]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);

        return back();
    }
}
