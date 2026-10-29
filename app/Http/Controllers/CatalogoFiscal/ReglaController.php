<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Inertia\Response;

/**
 * Pestaña 5: reglas de validación (6.2). Se ejecutan desde la fase 4.
 */
class ReglaController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/reglas', $anio, 'reglas', function () use ($anio) {
            $reglas = $this->catalogo->reglas($anio);
            $nombres = $this->nombresValidadores($reglas);

            return $reglas->map(fn (array $r) => [
                'codigo' => $r['codigo'],
                'descripcion' => $r['descripcion'],
                'severidad' => $r['severidad'],
                'implementada' => $r['implementada'],
                ...$this->fuente($r, $nombres),
            ])->values()->all();
        });
    }
}
