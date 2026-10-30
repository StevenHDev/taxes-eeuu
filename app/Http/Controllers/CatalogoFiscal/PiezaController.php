<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Models\CatalogoFiscal\CatalogoAnio;
use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\ParametroFiscal;
use App\Services\CatalogoFiscal\CatalogoFiscalEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Acciones comunes a las piezas del catálogo fiscal (F1.5): "Marcar como
 * validado contra el IRS" y activar o desactivar (nunca se borra).
 */
class PiezaController extends Controller
{
    use ArmaPaginaCatalogo;

    /** @var array<string, class-string<Model>> */
    private const TIPOS = [
        'preguntas' => CatalogoPregunta::class,
        'documentos' => CatalogoDocumento::class,
        'formularios' => CatalogoFormulario::class,
        'reglas' => CatalogoRegla::class,
        'parametros' => ParametroFiscal::class,
    ];

    public function __construct(private readonly CatalogoFiscalEditor $editor) {}

    public function validar(Request $request, int $anio, string $tipo, int $id): RedirectResponse
    {
        Gate::authorize('validar', CatalogoAnio::class);

        $pieza = $this->buscar($anio, $tipo, $id);
        $this->editor->validar($pieza, $request->user());

        return $this->guardado('Marcado como validado contra el IRS.');
    }

    public function activo(Request $request, int $anio, string $tipo, int $id): RedirectResponse
    {
        Gate::authorize('update', CatalogoAnio::class);
        abort_if($tipo === 'parametros', 404);

        $activo = $request->validate(['activo' => ['required', 'boolean']])['activo'];
        $pieza = $this->buscar($anio, $tipo, $id);

        // El 1040 siempre presente (9.5): no se desactiva.
        abort_if(! $activo && $pieza instanceof CatalogoFormulario && $pieza->siempre, 422, 'El Form 1040 no se puede desactivar.');

        $this->editor->cambiarActivo($pieza, (bool) $activo);

        return $this->guardado($activo ? 'Reactivado.' : 'Desactivado. Se conserva y se puede reactivar.');
    }

    private function buscar(int $anio, string $tipo, int $id): Model
    {
        $clase = self::TIPOS[$tipo] ?? abort(404);
        $pieza = $clase::query()->findOrFail($id);
        $this->delAnio($pieza, $anio);

        return $pieza;
    }
}
