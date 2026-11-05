<?php

namespace App\Services\Expediente;

use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\Credito8867;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Models\Consentimiento\Consentimiento;
use App\Models\Consentimiento\ConsentimientoTexto;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\SituacionEntrevista;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El respaldo de la debida diligencia (Form 8867): por cada pregunta que
 * sustenta un crédito, la respuesta, el canal, la fecha y quién; y los
 * documentos con quién los validó. Es determinista (no lo escribe una IA):
 * el IRS pide "cómo, cuándo y de quién". Sugiere los créditos y el preparador
 * confirma. El Form 8867 en sí se llena en TaxWise y la Parte VI no entra.
 * Lo sensible nunca sale en claro.
 */
class DebidaDiligencia
{
    /** La parte que siempre se muestra, sin importar los créditos. */
    private const PARTE_BASE = 'parte-i';

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly PasosEntrevista $pasos,
        private readonly EstadosExpediente $estados,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function vista(User $cliente, int $taxYear, bool $ingles): array
    {
        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();

        if ($entrevista === null) {
            return [
                'aplica' => false, 'creditos' => [], 'creditos_por' => null, 'creditos_en' => null,
                'entrevista' => null, 'secciones' => [], 'generado_en' => now()->toIso8601String(),
            ];
        }

        $expediente = Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();
        $situacion = $this->pasos->situacion($entrevista);
        $personas = $entrevista->personas()->get();

        $creditos = $this->creditos($expediente, $personas, $situacion);
        $reclamados = array_column(array_filter($creditos, fn (array $c) => $c['reclamado']), 'credito');
        $partes = [self::PARTE_BASE => null];

        foreach (Credito8867::cases() as $credito) {
            if (in_array($credito->value, $reclamados, true)) {
                $partes[$credito->parte()] = $credito->value;
            }
        }

        $preguntas = $this->catalogo->preguntas($taxYear);
        $respuestas = $entrevista->respuestas()->withCount('cambios')->get()
            ->keyBy(fn (EntrevistaRespuesta $r) => MotorEntrevista::clave($r->pregunta_codigo, $r->persona_id));
        $items = $entrevista->documentos()->vigentes()->with('revisor:id,name')->get();
        $actores = User::withTrashed()->whereIn('id', $respuestas->pluck('actor_id')->filter()->unique())->pluck('name', 'id');

        $secciones = [];

        foreach ($this->catalogo->formularios($taxYear)->firstWhere('codigo', 'f8867')['lineas'] ?? [] as $linea) {
            if (! array_key_exists($linea['linea'], $partes)) {
                continue;
            }

            $codigos = array_filter(explode(';', (string) $linea['origen_clave']));
            $delaParte = $preguntas->filter(fn (array $p) => in_array($p['codigo'], $codigos, true));

            $secciones[] = [
                'parte' => $linea['linea'],
                'titulo' => $linea['descripcion'],
                'credito' => $partes[$linea['linea']],
                'preguntas' => $this->filasDePreguntas($delaParte, $situacion, $personas, $respuestas, $actores, $cliente, $taxYear, $ingles),
                'documentos' => $this->filasDeDocumentos($delaParte, $items, $personas, $cliente, $taxYear, $ingles),
            ];
        }

        return [
            'aplica' => true,
            'creditos' => $creditos,
            'creditos_por' => $expediente?->creditos_8867_por === null ? null : User::withTrashed()->whereKey($expediente->creditos_8867_por)->value('name'),
            'creditos_en' => $expediente?->creditos_8867_en?->toIso8601String(),
            'entrevista' => [
                'iniciada_en' => $entrevista->iniciada_en->toIso8601String(),
                'canal_inicio' => $entrevista->canal_inicio->value,
                'confirmada_en' => $entrevista->confirmada_en?->toIso8601String(),
                'consentimiento' => $this->consentimiento($cliente, $taxYear),
            ],
            'secciones' => $secciones,
            'generado_en' => now()->toIso8601String(),
        ];
    }

    /**
     * Guarda los créditos que se reclaman, con su evento. Después de
     * presentar la declaración ya no se cambian.
     *
     * @param  list<Credito8867>  $creditos
     */
    public function guardarCreditos(Expediente $expediente, array $creditos, User $actor): void
    {
        $expediente = $this->estados->recalcular($expediente->user, $expediente->tax_year);

        abort_if($expediente->estado->trasPresentar(), 422, __('La declaración ya se presentó: los créditos ya no se pueden cambiar.'));

        $valor = array_values(array_unique(array_map(fn (Credito8867 $c) => $c->value, $creditos)));

        DB::transaction(function () use ($expediente, $valor, $actor) {
            $expediente->update(['creditos_8867' => $valor, 'creditos_8867_por' => $actor->id, 'creditos_8867_en' => now()]);

            ExpedienteEvento::query()->create([
                'expediente_id' => $expediente->id,
                'tipo' => TipoEventoExpediente::Decision,
                'actor_id' => $actor->id,
                'datos' => ['decision' => 'creditos_8867', 'valor' => $valor],
            ]);
        });
    }

    /**
     * @param  Collection<int, EntrevistaPersona>  $personas
     * @return list<array<string, mixed>>
     */
    private function creditos(?Expediente $expediente, Collection $personas, SituacionEntrevista $situacion): array
    {
        $guardados = $expediente?->creditos_8867;
        $sugerencias = $this->sugerencias($expediente, $personas, $situacion);

        return array_map(function (Credito8867 $credito) use ($guardados, $sugerencias) {
            $sugerido = isset($sugerencias[$credito->value]);

            return [
                'credito' => $credito->value,
                'etiqueta' => $credito->label(),
                'sugerido' => $sugerido,
                'motivo_sugerencia' => $sugerencias[$credito->value] ?? null,
                'reclamado' => $guardados === null ? $sugerido : in_array($credito->value, $guardados, true),
            ];
        }, Credito8867::cases());
    }

    /**
     * Qué créditos sugiere la plataforma y por qué. Las personas exentas
     * (clasificadas como no dependientes) no cuentan.
     *
     * @param  Collection<int, EntrevistaPersona>  $personas
     * @return array<string, string> crédito → motivo
     */
    private function sugerencias(?Expediente $expediente, Collection $personas, SituacionEntrevista $situacion): array
    {
        $sugerencias = [];
        $respuestas = $situacion->respuestas;

        if ($expediente?->estado_civil_irs === EstadoCivilIrs::Hoh) {
            $sugerencias[Credito8867::Hoh->value] = __('El estado civil decidido para el IRS es cabeza de familia (HOH)');
        }

        $ordenes = $personas->pluck('orden', 'id');
        $dependiente = $expediente?->personas()
            ->whereIn('clasificacion', [ClasificacionPersona::HijoCalificado->value, ClasificacionPersona::ParienteCalificado->value])
            ->whereIn('persona_id', $ordenes->keys())
            ->orderBy('id')
            ->first();

        if ($dependiente !== null) {
            $sugerencias[Credito8867::Ctc->value] = __('La persona :orden está clasificada como :clasificacion', [
                'orden' => $ordenes->get($dependiente->persona_id),
                'clasificacion' => mb_strtolower($dependiente->clasificacion->label()),
            ]);
        }

        if (($respuestas['F1'] ?? null) === 'si') {
            $sugerencias[Credito8867::Aotc->value] = __('Hubo gastos de educación superior (F1)');
        }

        $trabajo = array_values(array_filter(['D1', 'D15', 'D16'], fn (string $c) => ($respuestas[$c] ?? null) === 'si'));

        if ($trabajo !== [] && ($respuestas['A3'] ?? null) === 'ssn_valido') {
            $sugerencias[Credito8867::Eic->value] = __('Hubo ingreso del trabajo (:preguntas) y el SSN es válido para trabajar (A3)', ['preguntas' => implode(', ', $trabajo)]);
        }

        return $sugerencias;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $preguntas
     * @param  Collection<int, EntrevistaPersona>  $personas
     * @param  Collection<string, EntrevistaRespuesta>  $respuestas
     * @param  Collection<int, string>  $actores
     * @return list<array<string, mixed>>
     */
    private function filasDePreguntas(Collection $preguntas, SituacionEntrevista $situacion, Collection $personas, Collection $respuestas, Collection $actores, User $cliente, int $taxYear, bool $ingles): array
    {
        $filas = [];

        foreach ($preguntas as $pregunta) {
            foreach ($pregunta['por_persona'] ? $personas->all() : [null] as $persona) {
                if (! $situacion->esVisible($pregunta['codigo'], $persona?->id)) {
                    continue;
                }

                /** @var EntrevistaRespuesta|null $respuesta */
                $respuesta = $respuestas->get(MotorEntrevista::clave($pregunta['codigo'], $persona?->id));
                $sensible = (bool) $pregunta['sensible'];
                $texto = $ingles && ! empty($pregunta['texto_en']) ? $pregunta['texto_en'] : (string) $pregunta['texto_es'];

                $filas[] = [
                    'codigo' => $pregunta['codigo'],
                    'persona' => $persona === null ? null : ['orden' => $persona->orden, 'etiqueta' => $persona->etiqueta],
                    'texto' => str_replace('{anio}', (string) $taxYear, $texto),
                    'respondida' => $respuesta !== null,
                    'sensible' => $sensible,
                    'valor_texto' => $respuesta === null || $sensible ? null : TextoRespuesta::de($pregunta, $respuesta->valor, $ingles),
                    'canal' => $respuesta?->canal->value,
                    'respondida_en' => $respuesta?->respondida_en->toIso8601String(),
                    // Quien la escribió, solo si fue el personal; el cliente sale como nulo.
                    'actor' => $respuesta?->actor_id === null || $respuesta->actor_id === $cliente->id ? null : $actores->get($respuesta->actor_id),
                    'cambios' => $respuesta === null ? 0 : $respuesta->cambios_count,
                ];
            }
        }

        return $filas;
    }

    /**
     * Los documentos que activan las preguntas de la parte y están en el
     * checklist, sin repetir.
     *
     * @param  Collection<int, array<string, mixed>>  $preguntas
     * @param  Collection<int, EntrevistaDocumento>  $items
     * @param  Collection<int, EntrevistaPersona>  $personas
     * @return list<array<string, mixed>>
     */
    private function filasDeDocumentos(Collection $preguntas, Collection $items, Collection $personas, User $cliente, int $taxYear, bool $ingles): array
    {
        $codigos = $preguntas
            ->flatMap(fn (array $p) => $this->catalogo->activadosPor($taxYear, $p['codigo']))
            ->where('objetivo_tipo', 'documento')
            ->pluck('objetivo_codigo')
            ->unique();
        $catalogo = $this->catalogo->documentos($taxYear)->keyBy('codigo');
        $ordenes = $personas->pluck('orden', 'id');

        return $items
            ->filter(fn (EntrevistaDocumento $i) => $codigos->contains($i->documento_codigo))
            ->unique('id')
            ->sortBy(fn (EntrevistaDocumento $i) => [$i->documento_codigo, $ordenes->get($i->persona_id) ?? 0, $i->numero])
            ->map(function (EntrevistaDocumento $i) use ($catalogo, $ordenes, $cliente, $ingles) {
                $doc = $catalogo->get($i->documento_codigo);
                $nombre = $ingles ? ($doc['nombre_sencillo_en'] ?? null) : null;
                $validado = $i->estado === EstadoDocumento::Validado;

                return [
                    'item_id' => $i->id,
                    'codigo' => $i->documento_codigo,
                    'nombre' => $nombre ?: ($doc['nombre_sencillo_es'] ?? $doc['nombre_oficial'] ?? $i->documento_codigo),
                    'persona_orden' => $i->persona_id === null ? null : $ordenes->get($i->persona_id),
                    'estado' => $i->estado->value,
                    'validado_por' => $validado ? $i->revisor?->name : null,
                    'validado_en' => $validado ? $i->revisado_en?->toIso8601String() : null,
                    'enlace' => route('clientes.documentos.visor', ['cliente' => $cliente->id, 'item' => $i->id]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * La firma se guarda por cliente y año: se muestra la última del uso 7216.
     *
     * @return array{version: int, aceptado_en: string}|null
     */
    private function consentimiento(User $cliente, int $taxYear): ?array
    {
        $consentimiento = Consentimiento::query()
            ->with('texto:id,codigo,version')
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->whereHas('texto', fn ($q) => $q->where('codigo', ConsentimientoTexto::USO_7216))
            ->latest('aceptado_en')
            ->first();

        return $consentimiento === null ? null : [
            'version' => $consentimiento->texto->version,
            'aceptado_en' => $consentimiento->aceptado_en->toIso8601String(),
        ];
    }
}
