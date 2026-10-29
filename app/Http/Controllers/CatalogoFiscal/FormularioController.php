<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Inertia\Response;

/**
 * Pestaña 3: formularios que se llenan y sus líneas, con de dónde sale cada
 * una (4.0-B, 4.1–4.7). El 1040 va primero.
 */
class FormularioController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/formularios', $anio, 'formularios', function () use ($anio) {
            $formularios = $this->catalogo->formularios($anio);
            $nombres = $this->nombresValidadores($formularios);
            $activadoPor = $this->activadoPor($this->catalogo, $anio, 'formulario');

            return $formularios->map(fn (array $f) => [
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'para_que' => $f['para_que'],
                'siempre' => $f['siempre'],
                'ficha' => $f['ficha'],
                'lineas' => collect($f['lineas'])->map(fn (array $l) => [
                    'linea' => $l['linea'],
                    'descripcion' => $l['descripcion'],
                    'origen_tipo' => $l['origen_tipo'],
                    'origen' => $l['origen_clave'] ? array_values(array_filter(array_map('trim', explode(';', $l['origen_clave'])))) : [],
                ])->values()->all(),
                'activado_por' => $activadoPor->get($f['codigo'], []),
                ...$this->fuente($f, $nombres),
            ])->values()->all();
        });
    }
}
