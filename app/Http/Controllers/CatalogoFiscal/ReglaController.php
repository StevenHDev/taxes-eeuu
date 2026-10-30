<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogoFiscal\ReglaRequest;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Services\CatalogoFiscal\CatalogoFiscalEditor;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Pestaña 5: reglas de validación (6.2). Se ejecutan desde la fase 4.
 */
class ReglaController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(private readonly CatalogoFiscalEditor $editor) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/reglas', $anio, 'reglas', function () use ($anio) {
            $reglas = $this->piezas(CatalogoRegla::class, $anio, [], fn ($q) => $q->orderBy('orden'));
            $nombres = $this->nombresValidadores($reglas);

            return $reglas->map(fn (array $r) => [
                'id' => $r['id'],
                'activo' => $r['activo'],
                'codigo' => $r['codigo'],
                'descripcion' => $r['descripcion'],
                'severidad' => $r['severidad'],
                'implementada' => $r['implementada'],
                ...$this->fuente($r, $nombres),
            ])->values()->all();
        });
    }

    public function store(ReglaRequest $request, int $anio): RedirectResponse
    {
        $regla = new CatalogoRegla(['tax_year' => $anio]);
        $regla->setAttribute('orden', (int) CatalogoRegla::query()->delAnio($anio)->max('orden') + 1);
        $this->editor->guardar($regla, $request->validated());

        return $this->guardado("Regla {$regla->codigo} agregada.");
    }

    public function update(ReglaRequest $request, int $anio, CatalogoRegla $regla): RedirectResponse
    {
        $this->delAnio($regla, $anio);

        $reabierta = $this->editor->guardar($regla, $request->validated());

        return $this->guardado("Regla {$regla->codigo} guardada.", $reabierta);
    }
}
