<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogoFiscal\PreguntaRequest;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Services\CatalogoFiscal\CatalogoFiscalEditor;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Pestaña 1: preguntas de la entrevista (sección 3, 13614-C).
 */
class PreguntaController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(private readonly CatalogoFiscalEditor $editor) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/preguntas', $anio, 'preguntas', function () use ($anio) {
            $preguntas = $this->piezas(
                CatalogoPregunta::class,
                $anio,
                ['activaciones' => fn ($q) => $q->where('activo', true)],
                fn ($q) => $q->orderBy('bloque')->orderBy('orden'),
            );
            $nombres = $this->nombresValidadores($preguntas);

            return $preguntas->map(fn (array $p) => [
                'id' => $p['id'],
                'activo' => $p['activo'],
                'codigo' => $p['codigo'],
                'bloque' => $p['bloque'],
                'pantalla' => $p['pantalla'],
                'orden' => $p['orden'],
                'texto' => str_replace('{anio}', (string) $anio, $p['texto_es']),
                'texto_es' => $p['texto_es'],
                'texto_en' => $p['texto_en'],
                'ayuda' => $p['ayuda_es'] ? str_replace('{anio}', (string) $anio, $p['ayuda_es']) : null,
                'ayuda_es' => $p['ayuda_es'],
                'tipo_respuesta' => $p['tipo_respuesta'],
                'opciones' => collect($p['opciones'] ?? [])->map(fn ($o) => is_array($o)
                    ? ['valor' => (string) ($o['valor'] ?? ''), 'texto_es' => (string) ($o['texto_es'] ?? $o['valor'] ?? '')]
                    : ['valor' => (string) $o, 'texto_es' => (string) $o])->values()->all(),
                'nivel' => $p['nivel'],
                'por_persona' => $p['por_persona'],
                'sensible' => $p['sensible'],
                'condicion' => $p['condicion'],
                'efecto' => $p['efecto_es'],
                'activa' => collect($p['activaciones'])->map(fn (array $a) => [
                    'tipo' => $a['objetivo_tipo'],
                    'codigo' => $a['objetivo_codigo'],
                    'respuesta' => $a['respuesta'],
                ])->values()->all(),
                ...$this->fuente($p, $nombres),
            ])->values()->all();
        });
    }

    public function store(PreguntaRequest $request, int $anio): RedirectResponse
    {
        $pregunta = new CatalogoPregunta(['tax_year' => $anio]);
        $pregunta->setAttribute('orden', (int) CatalogoPregunta::query()->delAnio($anio)->where('bloque', $request->validated('bloque'))->max('orden') + 1);
        $this->editor->guardar($pregunta, $request->validated());

        return $this->guardado("Pregunta {$pregunta->codigo} agregada.");
    }

    public function update(PreguntaRequest $request, int $anio, CatalogoPregunta $pregunta): RedirectResponse
    {
        $this->delAnio($pregunta, $anio);

        $reabierta = $this->editor->guardar($pregunta, $request->validated());

        return $this->guardado("Pregunta {$pregunta->codigo} guardada.", $reabierta);
    }
}
