<?php

namespace App\Http\Controllers\CatalogoFiscal;

use App\Http\Controllers\CatalogoFiscal\Concerns\ArmaPaginaCatalogo;
use App\Http\Controllers\Controller;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Inertia\Response;

/**
 * Pestaña 1: preguntas de la entrevista (sección 3, 13614-C).
 */
class PreguntaController extends Controller
{
    use ArmaPaginaCatalogo;

    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    public function index(int $anio): Response
    {
        return $this->pagina('catalogo-fiscal/preguntas', $anio, 'preguntas', function () use ($anio) {
            $preguntas = $this->catalogo->preguntas($anio);
            $nombres = $this->nombresValidadores($preguntas);

            return $preguntas->map(fn (array $p) => [
                'codigo' => $p['codigo'],
                'bloque' => $p['bloque'],
                'pantalla' => $p['pantalla'],
                'texto' => str_replace('{anio}', (string) $anio, $p['texto_es']),
                'ayuda' => $p['ayuda_es'] ? str_replace('{anio}', (string) $anio, $p['ayuda_es']) : null,
                'tipo_respuesta' => $p['tipo_respuesta'],
                'opciones' => collect($p['opciones'] ?? [])->map(fn ($o) => is_array($o) ? ($o['texto_es'] ?? $o['valor'] ?? '') : $o)->values()->all(),
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
}
