<?php

namespace App\Services\Entrevista;

use App\Enums\CatalogoFiscal\NivelPregunta;
use App\Enums\CatalogoFiscal\TipoRespuesta;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Support\Collection;

/**
 * Los pasos de la entrevista en el portal (F2.5). Cada pantalla tiene dos
 * pasos: `entrada` (lo que todos responden) y `seguimiento` (lo que abrieron
 * esas respuestas); la pantalla 4 además tiene un paso por persona. Qué
 * preguntas entran en cada paso y cuál paso sigue lo decide el servidor con
 * MotorEntrevista: el navegador no evalúa condiciones.
 */
class PasosEntrevista
{
    public const PANTALLAS = 8;

    public const PANTALLA_PERSONAS = 4;

    /**
     * Pantallas de entrada que se contestan como una sola lista de casillas
     * con "Ninguna" (documento madre, sección 3; la 3 es la de la 2 original,
     * por la decisión 3 del plan).
     */
    public const CON_CASILLAS = [3, 5, 6, 7];

    /**
     * Preguntas de sí o no que no van en la lista de casillas: "¿Eras
     * ciudadano?" se contesta "sí" casi siempre, y dejarla sin marcar (o
     * marcar "Ninguna") abriría la pregunta de la visa.
     */
    public const APARTE = ['A6'];

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly MotorEntrevista $motor,
    ) {}

    public function situacion(Entrevista $entrevista): SituacionEntrevista
    {
        return $this->motor->situacion($entrevista->unsetRelation('respuestas')->unsetRelation('personas'));
    }

    /**
     * Las preguntas visibles del paso, en el orden del catálogo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function preguntas(Entrevista $entrevista, SituacionEntrevista $situacion, int $pantalla, string $paso, ?int $personaId = null): Collection
    {
        $codigos = collect($situacion->pantallas[$pantalla]['preguntas'] ?? [])
            ->where('persona_id', $personaId)
            ->pluck('codigo')
            ->all();

        return $this->catalogo->preguntas($entrevista->tax_year)
            ->whereIn('codigo', $codigos)
            ->filter(fn (array $p) => ($p['nivel'] === NivelPregunta::Entrada->value) === ($paso === 'entrada'))
            ->sortBy(fn (array $p) => [$p['bloque'], $p['orden'], $p['codigo']])
            ->values();
    }

    /**
     * Si una pregunta del paso se contesta en la lista de casillas: entonces
     * no marcarla guarda "no".
     *
     * @param  array<string, mixed>  $pregunta
     */
    public function esCasilla(array $pregunta, int $pantalla, string $paso): bool
    {
        return $paso === 'entrada'
            && in_array($pantalla, self::CON_CASILLAS, true)
            && ! in_array($pregunta['codigo'], self::APARTE, true)
            && in_array($pregunta['tipo_respuesta'], [TipoRespuesta::SiNo->value, TipoRespuesta::UstedConyugeNo->value], true);
    }

    /**
     * A dónde va el cliente después de guardar un paso. Se calcula con las
     * respuestas ya guardadas, así un seguimiento recién abierto aparece.
     */
    public function siguiente(Entrevista $entrevista, int $pantalla, string $paso, ?int $personaId = null): string
    {
        $situacion = $this->situacion($entrevista);

        if ($personaId !== null) {
            return $paso === 'entrada' && $this->preguntas($entrevista, $situacion, $pantalla, 'seguimiento', $personaId)->isNotEmpty()
                ? route('portal.entrevista.persona', ['persona' => $personaId, 'paso' => 'seguimiento'])
                : route('portal.entrevista.personas');
        }

        if ($paso === 'entrada' && $this->preguntas($entrevista, $situacion, $pantalla, 'seguimiento')->isNotEmpty()) {
            return route('portal.entrevista.paso', ['pantalla' => $pantalla, 'paso' => 'seguimiento']);
        }

        if ($pantalla === self::PANTALLA_PERSONAS && $this->conPersonas($situacion)) {
            return route('portal.entrevista.personas');
        }

        // Después de la última pantalla, la revisión para confirmar (F2.7).
        return $pantalla < self::PANTALLAS
            ? route('portal.entrevista.paso', ['pantalla' => $pantalla + 1])
            : route('portal.entrevista.resumen');
    }

    public function anterior(int $pantalla, string $paso, ?int $personaId = null): string
    {
        return match (true) {
            $personaId !== null && $paso === 'seguimiento' => route('portal.entrevista.persona', ['persona' => $personaId]),
            $personaId !== null => route('portal.entrevista.personas'),
            $paso === 'seguimiento' => route('portal.entrevista.paso', ['pantalla' => $pantalla]),
            $pantalla > 1 => route('portal.entrevista.paso', ['pantalla' => $pantalla - 1]),
            default => route('portal.entrevista.inicio'),
        };
    }

    /**
     * Si C0 es "sí": el bloque C se responde por persona.
     */
    public function conPersonas(SituacionEntrevista $situacion): bool
    {
        return ($situacion->respuestas['C0'] ?? null) === 'si';
    }

    /**
     * Si el cliente estaba casado (B1): las preguntas "usted o cónyuge" se
     * muestran con las dos columnas.
     */
    public function conConyuge(SituacionEntrevista $situacion): bool
    {
        return ($situacion->respuestas['B1'] ?? null) === 'casado';
    }

    /**
     * Avance por pantalla para el inicio y la barra de cada paso.
     *
     * @return list<array{numero: int, total: int, respondidas: int, estado: string}>
     */
    public function avance(SituacionEntrevista $situacion): array
    {
        return array_map(function (int $numero) use ($situacion) {
            $pantalla = $situacion->pantallas[$numero] ?? ['total' => 0, 'respondidas' => 0];

            return [
                'numero' => $numero,
                'total' => $pantalla['total'],
                'respondidas' => $pantalla['respondidas'],
                'estado' => match (true) {
                    $pantalla['total'] > 0 && $pantalla['respondidas'] >= $pantalla['total'] => 'completa',
                    $pantalla['respondidas'] > 0 => 'en_curso',
                    default => 'sin_empezar',
                },
            ];
        }, range(1, self::PANTALLAS));
    }

    /**
     * Las preguntas del paso como las recibe la página: texto en el idioma
     * del cliente, opciones, si es casilla, y la respuesta guardada con su
     * canal y fecha ("Respondiste por WhatsApp el 30 oct.").
     *
     * @param  Collection<int, array<string, mixed>>  $preguntas
     * @return list<array<string, mixed>>
     */
    public function paraPagina(Entrevista $entrevista, Collection $preguntas, int $pantalla, string $paso, ?int $personaId, bool $ingles): array
    {
        $respuestas = $entrevista->respuestas()
            ->whereIn('pregunta_codigo', $preguntas->pluck('codigo'))
            ->where('persona_id', $personaId)
            ->get()
            ->keyBy('pregunta_codigo');

        $texto = fn (?string $es, ?string $en) => $es === null ? null : str_replace('{anio}', (string) $entrevista->tax_year, $ingles && $en ? $en : $es);

        return $preguntas->map(function (array $p) use ($respuestas, $texto, $pantalla, $paso, $ingles) {
            /** @var EntrevistaRespuesta|null $respuesta */
            $respuesta = $respuestas->get($p['codigo']);

            return [
                'codigo' => $p['codigo'],
                'texto' => $texto($p['texto_es'], $p['texto_en']),
                'ayuda' => $texto($p['ayuda_es'], $p['ayuda_en'] ?? null),
                'tipo' => $p['tipo_respuesta'],
                'opciones' => collect($p['opciones'] ?? [])->map(fn (array $o) => [
                    'valor' => $o['valor'],
                    'texto' => $ingles && ! empty($o['texto_en']) ? $o['texto_en'] : $o['texto_es'],
                ])->values()->all(),
                'sensible' => (bool) $p['sensible'],
                'casilla' => $this->esCasilla($p, $pantalla, $paso),
                'valor' => $respuesta?->valor,
                'canal' => $respuesta?->canal->value,
                'respondida_en' => $respuesta?->respondida_en?->toIso8601String(),
            ];
        })->values()->all();
    }
}
