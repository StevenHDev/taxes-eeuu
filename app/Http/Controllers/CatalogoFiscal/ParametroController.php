<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Enums\CatalogoFiscal\GrupoParametro;
use App\Enums\CatalogoFiscal\TipoParametro;
use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogoFiscal\ParametroRequest;
use App\Models\ParametroFiscal;
use App\Services\CatalogoFiscal\CatalogoFiscalEditor;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Pestaña 4: parámetros del año (7.7), por grupo. `valor` nulo = pendiente.
 * Los parámetros no tienen `activo`: los usan las calculadoras por su clave.
 */
class ParametroController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(private readonly CatalogoFiscalEditor $editor) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/parametros', $anio, 'parametros', function () use ($anio) {
            $parametros = ParametroFiscal::query()->where('tax_year', $anio)->get()->map(fn (ParametroFiscal $p) => $p->toArray());
            $nombres = $this->nombresValidadores($parametros);
            $ordenGrupo = array_flip(array_map(fn (GrupoParametro $g) => $g->value, GrupoParametro::cases()));

            return $parametros
                ->sortBy(fn (array $p) => [$ordenGrupo[$p['grupo']] ?? 99, $p['orden']])
                ->map(fn (array $p) => [
                    'id' => $p['id'],
                    'activo' => true,
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

    public function update(ParametroRequest $request, int $anio, ParametroFiscal $parametro): RedirectResponse
    {
        $this->delAnio($parametro, $anio);

        $datos = $request->validated();

        // Los números llegan del formulario como texto: se guardan como número.
        if ($datos['valor'] !== null && in_array($parametro->tipo, [TipoParametro::Monto, TipoParametro::Numero, TipoParametro::Porcentaje], true)) {
            $datos['valor'] = 0 + $datos['valor'];
        }

        if ($parametro->tipo === TipoParametro::Tabla && is_array($datos['valor'])) {
            $datos['valor'] = array_map(fn (array $t) => ['desde' => 0 + $t['desde'], 'tasa' => 0 + $t['tasa']], array_values($datos['valor']));
        }

        $reabierta = $this->editor->guardar($parametro, $datos);

        return $this->guardado('Parámetro guardado.', $reabierta);
    }
}
