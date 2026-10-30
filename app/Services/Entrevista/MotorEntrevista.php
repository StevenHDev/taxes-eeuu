<?php

namespace App\Services\Entrevista;

use App\Enums\CatalogoFiscal\NivelPregunta;
use App\Enums\CatalogoFiscal\ObjetivoActivacion;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Support\Collection;

/**
 * Motor único de la entrevista (F2.2): decide qué preguntas se ven y qué
 * documentos y formularios quedan activados, a partir del catálogo fiscal
 * del año y de las respuestas. El portal, el agente y la vista del
 * preparador solo usan esto; ninguno tiene reglas propias de qué mostrar.
 *
 * Regla de visibilidad: una pregunta se ve si no es `no_se_pregunta`, su
 * `condicion` (si tiene) se cumple, y además es `entrada`, o tiene
 * `condicion` propia, o una respuesta visible la activa. Las preguntas
 * `por_persona` (bloque C) se ven una vez por persona y sus condiciones
 * miran las respuestas de esa misma persona.
 *
 * Lo que queda oculto no se borra (F0.3): sale de la situación y vuelve si
 * la condición se cumple otra vez.
 */
class MotorEntrevista
{
    /** @var Collection<string, array<string, mixed>> */
    private Collection $preguntas;

    /** @var array<string, list<array{origen: string, respuesta: mixed}>> pregunta objetivo → activaciones que la abren */
    private array $abrenPregunta;

    /** @var array<string, mixed> */
    private array $valores;

    /** @var list<int> */
    private array $personas;

    /** @var array<string, bool> */
    private array $memo;

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly EvaluadorCondicion $evaluador,
    ) {}

    public function situacion(Entrevista $entrevista): SituacionEntrevista
    {
        $entrevista->loadMissing(['personas', 'respuestas']);

        $this->preguntas = $this->catalogo->preguntas($entrevista->tax_year)->keyBy('codigo');
        $this->personas = $entrevista->personas->map(fn (EntrevistaPersona $p) => $p->id)->all();
        $this->valores = $entrevista->respuestas
            ->mapWithKeys(fn ($r) => [self::clave($r->pregunta_codigo, $r->persona_id) => $r->valor])
            ->all();
        $this->memo = [];
        $this->abrenPregunta = [];

        foreach ($this->preguntas as $pregunta) {
            foreach ($pregunta['activaciones'] as $activacion) {
                if ($activacion['objetivo_tipo'] === ObjetivoActivacion::Pregunta->value) {
                    $this->abrenPregunta[$activacion['objetivo_codigo']][] = ['origen' => $pregunta['codigo'], 'respuesta' => $activacion['respuesta']];
                }
            }
        }

        $pantallas = [];
        $respuestas = [];
        // Se arman por código como texto ("f:1040") para que "1040" no
        // quede como clave numérica; al final pasan a listas.
        $documentos = [];
        $formularios = $this->catalogo->formularios($entrevista->tax_year)
            ->where('siempre', true)
            ->mapWithKeys(fn (array $f) => ['f:'.$f['codigo'] => []])
            ->all();

        foreach ($this->preguntas->sortBy(fn (array $p) => [$p['pantalla'] ?? 99, $p['bloque'], $p['orden']]) as $codigo => $pregunta) {
            foreach ($pregunta['por_persona'] ? $this->personas : [null] as $personaId) {
                if (! $this->visible($codigo, $personaId)) {
                    continue;
                }

                $valor = $this->valores[self::clave($codigo, $personaId)] ?? null;
                $numero = (int) ($pregunta['pantalla'] ?? 0);

                $pantallas[$numero] ??= ['numero' => $numero, 'preguntas' => [], 'total' => 0, 'respondidas' => 0];
                $pantallas[$numero]['preguntas'][] = ['codigo' => $codigo, 'persona_id' => $personaId, 'sensible' => (bool) $pregunta['sensible'], 'respondida' => $valor !== null];
                $pantallas[$numero]['total']++;

                if ($valor === null) {
                    continue;
                }

                $pantallas[$numero]['respondidas']++;
                $respuestas[self::clave($codigo, $personaId)] = $valor;

                foreach ($pregunta['activaciones'] as $activacion) {
                    if (! ValorRespuesta::coincide($valor, $activacion['respuesta'])) {
                        continue;
                    }

                    match ($activacion['objetivo_tipo']) {
                        ObjetivoActivacion::Documento->value => $documentos['d:'.$activacion['objetivo_codigo']][] = $codigo,
                        ObjetivoActivacion::Formulario->value => $formularios['f:'.$activacion['objetivo_codigo']][] = $codigo,
                        default => null,
                    };
                }
            }
        }

        ksort($pantallas);
        $lista = fn (array $porCodigo) => array_map(
            fn (string $clave, array $origenes) => ['codigo' => substr($clave, 2), 'activado_por' => array_values(array_unique($origenes))],
            array_keys($porCodigo),
            $porCodigo,
        );

        return new SituacionEntrevista($pantallas, $lista($documentos), $lista($formularios), $respuestas);
    }

    public static function clave(string $codigo, ?int $personaId): string
    {
        return $personaId === null ? $codigo : "{$codigo}#{$personaId}";
    }

    private function visible(string $codigo, ?int $personaId): bool
    {
        $clave = self::clave($codigo, $personaId);

        if (array_key_exists($clave, $this->memo)) {
            return $this->memo[$clave];
        }

        // Corta un ciclo entre condiciones o activaciones: mientras se
        // evalúa, la pregunta cuenta como no visible.
        $this->memo[$clave] = false;

        $pregunta = $this->preguntas->get($codigo);

        return $this->memo[$clave] = $pregunta !== null
            && $pregunta['nivel'] !== NivelPregunta::NoSePregunta->value
            && (bool) $pregunta['por_persona'] === ($personaId !== null)
            && $this->evaluador->cumple($pregunta['condicion'], fn (string $otra) => $this->respuestaVisible($otra, $personaId))
            && ($pregunta['nivel'] === NivelPregunta::Entrada->value
                || $pregunta['condicion'] !== null
                || $this->laActivaAlguien($codigo, $personaId));
    }

    /**
     * Respuesta de otra pregunta vista desde `$personaId`: si la otra es por
     * persona, la de esa misma persona; si no se ve, cuenta como sin
     * responder.
     */
    private function respuestaVisible(string $codigo, ?int $personaId): mixed
    {
        $pregunta = $this->preguntas->get($codigo);

        if ($pregunta === null) {
            return null;
        }

        $persona = $pregunta['por_persona'] ? $personaId : null;

        if ($pregunta['por_persona'] && $persona === null) {
            return null;
        }

        return $this->visible($codigo, $persona) ? ($this->valores[self::clave($codigo, $persona)] ?? null) : null;
    }

    private function laActivaAlguien(string $codigo, ?int $personaId): bool
    {
        foreach ($this->abrenPregunta[$codigo] ?? [] as $activacion) {
            $origen = $this->preguntas->get($activacion['origen']);

            // Una pregunta general abierta desde el bloque C: basta con que
            // la active cualquier persona.
            $candidatas = match (true) {
                ! $origen['por_persona'] => [null],
                $personaId !== null => [$personaId],
                default => $this->personas,
            };

            foreach ($candidatas as $persona) {
                if (ValorRespuesta::coincide($this->respuestaVisible($activacion['origen'], $persona), $activacion['respuesta'])) {
                    return true;
                }
            }
        }

        return false;
    }
}
