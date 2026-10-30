<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogoFiscal\DocumentoRequest;
use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\CatalogoFiscal\CatalogoFiscalEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

/**
 * Pestaña 2: documentos que se le pueden pedir al cliente y sus casillas
 * (4.0-A, 4.1–4.7, 5.2), con las preguntas que los activan.
 */
class DocumentoController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly CatalogoFiscalEditor $editor,
    ) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/documentos', $anio, 'documentos', function () use ($anio) {
            // Orden de los grupos del inventario 4.0-A (identidad primero, como el checklist del cliente, 5.1).
            $orden = array_flip(DocumentoRequest::GRUPOS);
            $documentos = $this->piezas(
                CatalogoDocumento::class,
                $anio,
                ['casillas' => fn ($q) => $q->where('activo', true)],
                fn ($q) => $q->orderBy('orden'),
            )->sortBy(fn (array $d) => [$orden[$d['grupo']] ?? 99, $d['orden']]);
            $nombres = $this->nombresValidadores($documentos);
            $activadoPor = $this->activadoPor($this->catalogo, $anio, 'documento');

            return $documentos->map(fn (array $d) => [
                'id' => $d['id'],
                'activo' => $d['activo'],
                'codigo' => $d['codigo'],
                'grupo' => $d['grupo'],
                'nombre' => $d['nombre_sencillo_es'],
                'nombre_oficial' => $d['nombre_oficial'],
                'emisor' => $d['emisor_es'],
                'fecha_llegada' => $d['fecha_llegada'],
                'si_no_llega' => $d['si_no_llega_es'],
                'formatos' => $d['formatos'] ?? [],
                'uno_por' => $d['uno_por'],
                'casillas' => collect($d['casillas'])->map(fn (array $c) => [
                    'casilla' => $c['casilla'],
                    'descripcion' => $c['descripcion'],
                    'tipo_dato' => $c['tipo_dato'],
                    'clave' => $c['clave'],
                ])->values()->all(),
                'activado_por' => $activadoPor->get($d['codigo'], []),
                ...$this->fuente($d, $nombres),
            ])->values()->all();
        });
    }

    public function store(DocumentoRequest $request, int $anio): RedirectResponse
    {
        $documento = new CatalogoDocumento(['tax_year' => $anio]);
        $documento->setAttribute('orden', (int) CatalogoDocumento::query()->delAnio($anio)->where('grupo', $request->validated('grupo'))->max('orden') + 1);

        DB::transaction(function () use ($documento, $request, $anio) {
            $this->editor->guardar($documento, Arr::except($request->validated(), 'casillas'));
            $this->editor->sincronizarHijos($documento->casillas(), $request->validated('casillas') ?? [], 'casilla', $anio);
        });

        return $this->guardado("Documento {$documento->codigo} agregado.");
    }

    public function update(DocumentoRequest $request, int $anio, CatalogoDocumento $documento): RedirectResponse
    {
        $this->delAnio($documento, $anio);

        $reabierta = DB::transaction(function () use ($documento, $request, $anio) {
            $reabierta = $this->editor->guardar($documento, Arr::except($request->validated(), 'casillas'));
            $cambiaronCasillas = $this->editor->sincronizarHijos($documento->casillas(), $request->validated('casillas') ?? [], 'casilla', $anio);

            return $reabierta || ($cambiaronCasillas && $this->editor->reabrirSiValidada($documento));
        });

        return $this->guardado("Documento {$documento->codigo} guardado.", $reabierta);
    }
}
