<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogoFiscal\FormularioRequest;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\CatalogoFiscal\CatalogoFiscalEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

/**
 * Pestaña 3: formularios que se llenan y sus líneas, con de dónde sale cada
 * una (4.0-B, 4.1–4.7). El 1040 va primero y no se crea desde aquí: es el
 * único "siempre".
 */
class FormularioController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly CatalogoFiscalEditor $editor,
    ) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/formularios', $anio, 'formularios', function () use ($anio) {
            $formularios = $this->piezas(
                CatalogoFormulario::class,
                $anio,
                ['lineas' => fn ($q) => $q->where('activo', true)],
                fn ($q) => $q->orderByDesc('siempre')->orderBy('orden'),
            );
            $nombres = $this->nombresValidadores($formularios);
            $activadoPor = $this->activadoPor($this->catalogo, $anio, 'formulario');

            return $formularios->map(fn (array $f) => [
                'id' => $f['id'],
                'activo' => $f['activo'],
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'para_que' => $f['para_que'],
                'siempre' => $f['siempre'],
                'ficha' => $f['ficha'],
                'lineas' => collect($f['lineas'])->map(fn (array $l) => [
                    'linea' => $l['linea'],
                    'descripcion' => $l['descripcion'],
                    'origen_tipo' => $l['origen_tipo'],
                    'origen_clave' => $l['origen_clave'],
                    'origen' => $l['origen_clave'] ? array_values(array_filter(array_map('trim', explode(';', $l['origen_clave'])))) : [],
                ])->values()->all(),
                'activado_por' => $activadoPor->get($f['codigo'], []),
                ...$this->fuente($f, $nombres),
            ])->values()->all();
        });
    }

    public function store(FormularioRequest $request, int $anio): RedirectResponse
    {
        $formulario = new CatalogoFormulario(['tax_year' => $anio, 'siempre' => false]);
        $formulario->setAttribute('orden', (int) CatalogoFormulario::query()->delAnio($anio)->max('orden') + 1);

        DB::transaction(function () use ($formulario, $request, $anio) {
            $this->editor->guardar($formulario, Arr::except($request->validated(), 'lineas'));
            $this->editor->sincronizarHijos($formulario->lineas(), $request->validated('lineas') ?? [], 'linea', $anio);
        });

        return $this->guardado("Formulario {$formulario->codigo} agregado.");
    }

    public function update(FormularioRequest $request, int $anio, CatalogoFormulario $formulario): RedirectResponse
    {
        $this->delAnio($formulario, $anio);

        $reabierta = DB::transaction(function () use ($formulario, $request, $anio) {
            $reabierta = $this->editor->guardar($formulario, Arr::except($request->validated(), 'lineas'));
            $cambiaronLineas = $this->editor->sincronizarHijos($formulario->lineas(), $request->validated('lineas') ?? [], 'linea', $anio);

            return $reabierta || ($cambiaronLineas && $this->editor->reabrirSiValidada($formulario));
        });

        return $this->guardado("Formulario {$formulario->codigo} guardado.", $reabierta);
    }
}
