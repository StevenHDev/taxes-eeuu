<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Inertia\Response;

/**
 * Pestaña 2: documentos que se le pueden pedir al cliente y sus casillas
 * (4.0-A, 4.1–4.7, 5.2), con las preguntas que los activan.
 */
class DocumentoController extends Controller
{
    use ArmaPaginaCatalogo;

    /**
     * Orden de los grupos del inventario 4.0-A (lo de identidad primero,
     * como en el checklist del cliente, 5.1).
     */
    private const GRUPOS = ['identidad', 'trabajo', 'negocio', 'jubilacion', 'inversiones', 'otros_ingresos', 'gastos', 'situaciones', 'pagos'];

    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/documentos', $anio, 'documentos', function () use ($anio) {
            $documentos = $this->catalogo->documentos($anio);
            $nombres = $this->nombresValidadores($documentos);
            $activadoPor = $this->activadoPor($this->catalogo, $anio, 'documento');

            $orden = array_flip(self::GRUPOS);

            return $documentos
                ->sortBy(fn (array $d) => [$orden[$d['grupo']] ?? 99, $d['orden']])
                ->map(fn (array $d) => [
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
                        'clave' => $c['clave'],
                    ])->values()->all(),
                    'activado_por' => $activadoPor->get($d['codigo'], []),
                    ...$this->fuente($d, $nombres),
                ])->values()->all();
        });
    }
}
