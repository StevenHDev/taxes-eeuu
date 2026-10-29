<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Enums\CatalogoFiscal\GrupoParametro;
use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Inertia\Response;

/**
 * Pestaña 4: parámetros del año (7.7), por grupo. `valor` nulo = pendiente.
 */
class ParametroController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/parametros', $anio, 'parametros', function () use ($anio) {
            $parametros = $this->catalogo->parametros($anio);
            $nombres = $this->nombresValidadores($parametros);
            $ordenGrupo = array_flip(array_map(fn (GrupoParametro $g) => $g->value, GrupoParametro::cases()));

            return $parametros
                ->sortBy(fn (array $p) => [$ordenGrupo[$p['grupo']] ?? 99, $p['orden']])
                ->map(fn (array $p) => [
                    'codigo' => "{$p['categoria']}.{$p['clave']}",
                    'grupo' => $p['grupo'],
                    'nombre' => $p['nombre_es'] ?? "{$p['categoria']}.{$p['clave']}",
                    'valor' => $p['valor'],
                    'tipo' => $p['tipo'],
                    'condicion' => $p['condicion_es'],
                    'formulario_linea' => $p['formulario_linea'],
                    ...$this->fuente($p, $nombres),
                ])->values()->all();
        });
    }
}
