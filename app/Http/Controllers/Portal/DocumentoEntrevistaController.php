<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\AgregarOtroDocumentoRequest;
use App\Http\Requests\Portal\MarcarNoAplicaRequest;
use App\Http\Requests\Portal\OtroDocumentoRequest;
use App\Http\Requests\Portal\SubirDocumentoRequest;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\TransicionNoPermitida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        private readonly ChecklistDocumentos $checklist,
    ) {}

    public function index(Request $request): Response
    {
        $entrevista = $this->guardador->entrevistaDe($request->user(), CanalRespuesta::Portal);
        $situacion = $this->pasos->situacion($entrevista);
        $this->checklist->sincronizar($entrevista, $situacion);

        return Inertia::render('portal/entrevista/documentos', [
            'taxYear' => $entrevista->tax_year,
            'documentos' => $this->documentos->lista($entrevista, $situacion, str_starts_with(app()->getLocale(), 'en')),
            'entrevistaCompleta' => $situacion->completa(),
        ]);
    }

    public function store(SubirDocumentoRequest $request): RedirectResponse
    {
        $archivos = $request->file('archivos');
        $item = $request->item();

        DB::transaction(function () use ($item, $archivos) {
            foreach ($archivos as $archivo) {
                $this->documentos->subir($item, $archivo);
            }
        });

        return $this->subidos(count($archivos));
    }

    public function noAplica(MarcarNoAplicaRequest $request): RedirectResponse
    {
        try {
            $this->checklist->marcarNoAplica($request->item(), (string) $request->validated('motivo'));
        } catch (TransicionNoPermitida $e) {
            throw ValidationException::withMessages(['motivo' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listo, lo marcamos como "no aplica".')]);

        return back();
    }

    public function agregarOtro(AgregarOtroDocumentoRequest $request): RedirectResponse
    {
        $item = $request->item();

        try {
            $this->checklist->agregar($request->entrevista(), $item->documento_codigo, $item->persona_id);
        } catch (TransicionNoPermitida $e) {
            throw ValidationException::withMessages(['agregar' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agregamos otro a tu lista.')]);

        return back();
    }

    public function otroDocumento(OtroDocumentoRequest $request): RedirectResponse
    {
        $archivos = $request->file('archivos');

        try {
            DB::transaction(function () use ($request, $archivos) {
                $item = $this->checklist->agregar($request->entrevista(), EntrevistaDocumento::OTRO, null, trim((string) $request->validated('descripcion')));

                foreach ($archivos as $archivo) {
                    $this->documentos->subir($item, $archivo);
                }
            });
        } catch (TransicionNoPermitida $e) {
            throw ValidationException::withMessages(['descripcion' => $e->getMessage()]);
        }

        return $this->subidos(count($archivos));
    }

    private function subidos(int $cuantos): RedirectResponse
    {
        // Con __() y no trans_choice(): sin un lang/es.json, trans_choice()
        // cae al inglés aunque el cliente use español.
        $mensaje = $cuantos === 1 ? __('Subiste 1 archivo.') : __('Subiste :n archivos.', ['n' => $cuantos]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);

        return back();
    }
}
