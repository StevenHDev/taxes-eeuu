<?php

namespace App\Services\Entrevista;

use App\Enums\CatalogoFiscal\NivelPregunta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Models\ClienteAtestacion;
use App\Models\Entrevista\Entrevista;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Revisión y confirmación de la entrevista en el portal (F2.7). El resumen
 * muestra todo lo visible por pantalla; confirmar exige que no falte
 * ninguna pregunta (los documentos pendientes no bloquean) y deja una
 * atestación con el hash de lo confirmado. Si después cambia cualquier
 * respuesta, RespuestaGuardador reabre la entrevista y hay que confirmar de
 * nuevo; la confirmación vieja se conserva.
 */
class ConfirmacionEntrevista
{
    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly PasosEntrevista $pasos,
    ) {}

    /**
     * Las respuestas visibles por pantalla, con el enlace al paso donde se
     * cambian. Los valores van crudos: la página los muestra en el idioma
     * del cliente.
     *
     * @return list<array{numero: int, preguntas: list<array<string, mixed>>}>
     */
    public function resumen(Entrevista $entrevista, SituacionEntrevista $situacion, bool $ingles): array
    {
        $catalogo = $this->catalogo->preguntas($entrevista->tax_year)->keyBy('codigo');
        $personas = $entrevista->personas()->pluck('orden', 'id');
        $texto = fn (?string $es, ?string $en) => str_replace('{anio}', (string) $entrevista->tax_year, $ingles && $en ? $en : (string) $es);

        return collect($situacion->pantallas)->map(fn (array $pantalla) => [
            'numero' => $pantalla['numero'],
            'preguntas' => collect($pantalla['preguntas'])->map(function (array $p) use ($catalogo, $personas, $situacion, $texto, $ingles) {
                $pregunta = $catalogo->get($p['codigo']);
                $paso = $pregunta['nivel'] === NivelPregunta::Entrada->value ? 'entrada' : 'seguimiento';

                return [
                    'codigo' => $p['codigo'],
                    'persona_id' => $p['persona_id'],
                    'persona_orden' => $p['persona_id'] === null ? null : $personas->get($p['persona_id']),
                    'texto' => $texto($pregunta['texto_es'], $pregunta['texto_en']),
                    'tipo' => $pregunta['tipo_respuesta'],
                    'opciones' => collect($pregunta['opciones'] ?? [])->map(fn (array $o) => [
                        'valor' => $o['valor'],
                        'texto' => $ingles && ! empty($o['texto_en']) ? $o['texto_en'] : $o['texto_es'],
                    ])->values()->all(),
                    'sensible' => $p['sensible'],
                    'valor' => $situacion->respuestas[MotorEntrevista::clave($p['codigo'], $p['persona_id'])] ?? null,
                    'url' => $p['persona_id'] !== null
                        ? route('portal.entrevista.persona', ['persona' => $p['persona_id'], 'paso' => $paso])
                        : route('portal.entrevista.paso', ['pantalla' => $pregunta['pantalla'], 'paso' => $paso]),
                ];
            })->values()->all(),
        ])->values()->all();
    }

    /**
     * Huella de lo que se confirma: las respuestas visibles, ordenadas.
     */
    public function hash(SituacionEntrevista $situacion): string
    {
        $respuestas = $situacion->respuestas;
        ksort($respuestas);

        return hash('sha256', json_encode($respuestas, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @throws LogicException si falta alguna pregunta visible
     */
    public function confirmar(Entrevista $entrevista, string $declaracion): ClienteAtestacion
    {
        return DB::transaction(function () use ($entrevista, $declaracion) {
            Entrevista::query()->whereKey($entrevista->id)->lockForUpdate()->first();
            $situacion = $this->pasos->situacion($entrevista);

            if (! $situacion->completa()) {
                throw new LogicException(__('Todavía faltan preguntas por responder.'));
            }

            $atestacion = ClienteAtestacion::query()->create([
                'user_id' => $entrevista->user_id,
                'tax_year' => $entrevista->tax_year,
                'canal' => 'portal',
                'entrevista_id' => $entrevista->id,
                'respuesta_cliente' => $declaracion,
                'resumen_hash' => $this->hash($situacion),
                'confirmado_en' => now(),
            ]);

            $entrevista->update(['estado' => EstadoEntrevista::Confirmada, 'confirmada_en' => $atestacion->confirmado_en]);

            return $atestacion;
        });
    }
}
