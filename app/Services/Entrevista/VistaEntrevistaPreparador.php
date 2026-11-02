<?php

namespace App\Services\Entrevista;

use App\Models\Consentimiento\Consentimiento;
use App\Models\Consentimiento\ConsentimientoTexto;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\Entrevista\EntrevistaRespuestaCambio;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;

/**
 * La entrevista de un cliente como la ve el preparador en la ficha (F2.9):
 * estado, consentimiento, respuestas visibles por pantalla con su canal y
 * fecha, personas, formularios y documentos activados. Las respuestas
 * sensibles nunca salen en claro: se revelan una por una con contraseña
 * (EntrevistaRespuestaReveal).
 */
class VistaEntrevistaPreparador
{
    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly PasosEntrevista $pasos,
        private readonly DocumentosEntrevista $documentos,
    ) {}

    /**
     * @return array<string, mixed>|null null si el cliente no empezó la entrevista de ese año
     */
    public function para(User $cliente, int $taxYear, bool $ingles): ?array
    {
        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();

        if ($entrevista === null) {
            return null;
        }

        $situacion = $this->pasos->situacion($entrevista);
        $catalogo = $this->catalogo->preguntas($taxYear)->keyBy('codigo');
        $personas = $entrevista->personas()->get();
        $ordenes = $personas->pluck('orden', 'id');
        $texto = fn (?string $es, ?string $en) => str_replace('{anio}', (string) $taxYear, $ingles && $en ? $en : (string) $es);

        $respuestas = $entrevista->respuestas()
            ->withCount('cambios')
            ->get()
            ->keyBy(fn (EntrevistaRespuesta $r) => MotorEntrevista::clave($r->pregunta_codigo, $r->persona_id));

        // La firma se guarda por cliente y año (Consentimientos::firmar), no
        // en la entrevista: se muestra la última del uso 7216.
        $consentimiento = Consentimiento::query()
            ->with('texto:id,codigo,version,provisional')
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->whereHas('texto', fn ($q) => $q->where('codigo', ConsentimientoTexto::USO_7216))
            ->latest('aceptado_en')
            ->first();

        return [
            'tax_year' => $taxYear,
            'estado' => $entrevista->estado->value,
            'canal_inicio' => $entrevista->canal_inicio->value,
            'iniciada_en' => $entrevista->iniciada_en->toIso8601String(),
            'confirmada_en' => $entrevista->confirmada_en?->toIso8601String(),
            'consentimiento' => $consentimiento === null ? null : [
                'version' => $consentimiento->texto->version,
                'provisional' => (bool) $consentimiento->texto->provisional,
                'aceptado_en' => $consentimiento->aceptado_en->toIso8601String(),
            ],
            'con_conyuge' => $this->pasos->conConyuge($situacion),
            'personas' => $personas->map(fn (EntrevistaPersona $p) => [
                'id' => $p->id,
                'orden' => $p->orden,
                'etiqueta' => $p->etiqueta,
            ])->values()->all(),
            'formularios' => array_column($situacion->formularios, 'codigo'),
            'pantallas' => collect($situacion->pantallas)->map(fn (array $pantalla) => [
                'numero' => $pantalla['numero'],
                'total' => $pantalla['total'],
                'respondidas' => $pantalla['respondidas'],
                'preguntas' => collect($pantalla['preguntas'])->map(function (array $p) use ($catalogo, $ordenes, $respuestas, $texto, $ingles) {
                    $pregunta = $catalogo->get($p['codigo']);
                    /** @var EntrevistaRespuesta|null $respuesta */
                    $respuesta = $respuestas->get(MotorEntrevista::clave($p['codigo'], $p['persona_id']));

                    return [
                        'respuesta_id' => $respuesta?->id,
                        'codigo' => $p['codigo'],
                        'persona_id' => $p['persona_id'],
                        'persona_orden' => $p['persona_id'] === null ? null : $ordenes->get($p['persona_id']),
                        'texto' => $texto($pregunta['texto_es'], $pregunta['texto_en']),
                        'ayuda' => $pregunta['ayuda_es'] === null ? null : $texto($pregunta['ayuda_es'], $pregunta['ayuda_en'] ?? null),
                        'tipo' => $pregunta['tipo_respuesta'],
                        'opciones' => collect($pregunta['opciones'] ?? [])->map(fn (array $o) => [
                            'valor' => $o['valor'],
                            'texto' => $ingles && ! empty($o['texto_en']) ? $o['texto_en'] : $o['texto_es'],
                        ])->values()->all(),
                        'sensible' => $p['sensible'],
                        'respondida' => $respuesta !== null,
                        // Lo sensible se revela aparte, con contraseña.
                        'valor' => $respuesta === null || $p['sensible'] ? null : $respuesta->valor,
                        'canal' => $respuesta?->canal->value,
                        'respondida_en' => $respuesta?->respondida_en->toIso8601String(),
                        'cambios' => $respuesta === null ? 0 : $respuesta->cambios_count,
                    ];
                })->values()->all(),
            ])->values()->all(),
            'documentos' => collect($this->documentos->lista($entrevista, $situacion, $ingles))
                ->map(fn (array $d) => [
                    'codigo' => $d['codigo'],
                    'persona_orden' => $d['persona_orden'],
                    'nombre' => $d['nombre'],
                    'archivos' => $d['archivos'],
                ])->all(),
        ];
    }

    /**
     * El historial de una respuesta, del más nuevo al más viejo. Los valores
     * de una pregunta sensible no salen.
     *
     * @return list<array<string, mixed>>
     */
    public function historial(EntrevistaRespuesta $respuesta): array
    {
        $sensible = (bool) ($this->catalogo->preguntas($respuesta->entrevista->tax_year)
            ->firstWhere('codigo', $respuesta->pregunta_codigo)['sensible'] ?? true);

        $cambios = $respuesta->cambios()->latest('id')->get();
        $actores = User::withTrashed()->whereIn('id', $cambios->pluck('actor_id')->filter()->unique())->pluck('name', 'id');

        return $cambios->map(fn (EntrevistaRespuestaCambio $c) => [
            'id' => $c->id,
            'valor_anterior' => $sensible ? null : $c->valor_anterior,
            'valor_nuevo' => $sensible ? null : $c->valor_nuevo,
            'sensible' => $sensible,
            'canal' => $c->canal->value,
            'actor' => $c->actor_id === null ? null : $actores->get($c->actor_id),
            'creado_en' => $c->created_at->toIso8601String(),
        ])->values()->all();
    }
}
