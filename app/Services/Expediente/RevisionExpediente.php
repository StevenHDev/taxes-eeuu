<?php

namespace App\Services\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\PuntoRevision;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\Expediente\ExpedientePersona;
use App\Models\Expediente\ExpedienteRevisionPunto;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Lectura\ExplicadorExcepcion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Todo lo que el preparador hace en la pestaña Revisión (plan de F5, 5.3):
 * decidir el estado civil para el IRS y la clasificación de cada persona, con
 * motivo; confirmar los puntos manuales del checklist 7.2 y mover el
 * expediente por los estados manuales. Cada escritura recalcula primero el
 * expediente (`EstadosExpediente`), corre en una transacción y deja su evento.
 * Los motivos son texto del preparador; los eventos nunca llevan SSN ni montos.
 */
class RevisionExpediente
{
    /** Respuestas del cliente que se muestran junto al estado civil. */
    private const RESPUESTAS_ESTADO_CIVIL = ['B1', 'B2', 'B3', 'B5', 'B6'];

    /** Respuestas por persona (nunca C1, C2 ni C9: son sensibles). */
    private const RESPUESTAS_PERSONA = ['C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C11', 'C12', 'C13', 'C14'];

    /**
     * @var array<string, array{0: list<EstadoExpediente>, 1: EstadoExpediente}>
     */
    private const TRANSICIONES = [
        'empezar' => [[EstadoExpediente::ListoParaPreparar], EstadoExpediente::EnRevision],
        'preparado' => [[EstadoExpediente::EnRevision], EstadoExpediente::PreparadoEnTaxwise],
        'a_firma' => [[EstadoExpediente::PreparadoEnTaxwise], EstadoExpediente::EsperandoFirma],
        'presentado' => [[EstadoExpediente::PreparadoEnTaxwise, EstadoExpediente::EsperandoFirma], EstadoExpediente::Presentado],
        'aceptado' => [[EstadoExpediente::Presentado], EstadoExpediente::Aceptado],
        'rechazado' => [[EstadoExpediente::Presentado], EstadoExpediente::Rechazado],
        'volver_a_revision' => [[EstadoExpediente::Rechazado], EstadoExpediente::EnRevision],
    ];

    public function __construct(
        private readonly EstadosExpediente $estados,
        private readonly DiagnosticoExpediente $diagnostico,
        private readonly MotorEntrevista $motor,
        private readonly CatalogoFiscal $catalogo,
        private readonly ExplicadorExcepcion $explicador,
    ) {}

    /**
     * Lo que muestra la pestaña Revisión. Solo lee: si todavía no hay
     * expediente, el estado sale del diagnóstico y no se crea nada.
     *
     * @return array<string, mixed>
     */
    public function vista(User $cliente, int $taxYear, bool $ingles): array
    {
        $diagnostico = $this->diagnostico->para($cliente, $taxYear);
        $expediente = Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first()
            ?? new Expediente(['user_id' => $cliente->id, 'tax_year' => $taxYear, 'estado' => $diagnostico->estado, 'estado_desde' => now()]);
        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();

        $respuestas = $entrevista === null ? [] : $this->motor->situacion($entrevista->unsetRelation('respuestas')->unsetRelation('personas'))->respuestas;
        $preguntas = $this->catalogo->preguntas($taxYear)->keyBy('codigo');

        $puntos = $this->puntosCon($expediente, $diagnostico, $entrevista);
        $faltan = count(array_filter($puntos, fn (array $p) => $p['requerido'] && ! $p['cumplido']));
        $puedeAprobar = $faltan === 0;

        $enlaces = $this->enlaces($cliente, $taxYear);

        return [
            'estado' => $expediente->estado->value,
            'acciones' => $this->acciones($expediente->estado, $puedeAprobar, $faltan),
            'decisiones_abiertas' => ! $expediente->estado->trasPresentar(),
            'estado_civil' => [
                'valor' => $expediente->estado_civil_irs?->value,
                'motivo' => $expediente->estado_civil_motivo,
                'por' => $expediente->decisorEstadoCivil?->name,
                'en' => $expediente->estado_civil_en?->toIso8601String(),
                'opciones' => array_map(fn (EstadoCivilIrs $e) => ['valor' => $e->value, 'etiqueta' => $e->label()], EstadoCivilIrs::cases()),
                'respuestas_cliente' => $this->respuestasEn(self::RESPUESTAS_ESTADO_CIVIL, null, $respuestas, $preguntas, $taxYear, $ingles),
            ],
            'personas' => $this->personas($expediente, $entrevista, $respuestas, $preguntas, $taxYear, $ingles),
            'clasificaciones' => array_map(fn (ClasificacionPersona $c) => ['valor' => $c->value, 'etiqueta' => $c->label()], ClasificacionPersona::cases()),
            'puntos' => $this->conEnlaces($puntos, $enlaces, $expediente->estado),
            'no_aplica' => $diagnostico->noAplica,
            'puede_aprobar' => $puedeAprobar,
            'faltan' => $faltan,
            'diagnostico' => $diagnostico->toArray(),
            'enlaces' => $enlaces,
        ];
    }

    /**
     * Los 7 puntos del checklist con su estado.
     *
     * @return list<array<string, mixed>>
     */
    public function puntos(Expediente $expediente): array
    {
        $cliente = User::withTrashed()->findOrFail($expediente->user_id);
        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $expediente->tax_year)->first();
        $diagnostico = $this->diagnostico->para($cliente, $expediente->tax_year);

        return $this->conEnlaces(
            $this->puntosCon($expediente, $diagnostico, $entrevista),
            $this->enlaces($cliente, $expediente->tax_year),
            $expediente->estado,
        );
    }

    public function puedeAprobar(Expediente $expediente): bool
    {
        return array_filter($this->puntos($expediente), fn (array $p) => $p['requerido'] && ! $p['cumplido']) === [];
    }

    public function decidirEstadoCivil(Expediente $expediente, EstadoCivilIrs $valor, string $motivo, User $actor): void
    {
        $this->escribir($expediente, function (Expediente $e) use ($valor, $motivo, $actor) {
            $this->exigirDecisionesAbiertas($e);

            $e->update([
                'estado_civil_irs' => $valor,
                'estado_civil_motivo' => $motivo,
                'estado_civil_por' => $actor->id,
                'estado_civil_en' => now(),
            ]);

            $this->eventoDeDecision($e, $actor, $motivo, ['decision' => 'estado_civil', 'valor' => $valor->value]);
        });
    }

    public function clasificarPersona(Expediente $expediente, EntrevistaPersona $persona, ClasificacionPersona $clasificacion, string $motivo, User $actor): void
    {
        $this->escribir($expediente, function (Expediente $e) use ($persona, $clasificacion, $motivo, $actor) {
            $this->exigirDecisionesAbiertas($e);

            ExpedientePersona::query()->updateOrCreate(
                ['expediente_id' => $e->id, 'persona_id' => $persona->id],
                ['clasificacion' => $clasificacion, 'motivo' => $motivo, 'decidido_por' => $actor->id, 'decidido_en' => now()],
            );

            $this->eventoDeDecision($e, $actor, $motivo, ['decision' => 'persona', 'persona_id' => $persona->id, 'valor' => $clasificacion->value]);
        });
    }

    public function confirmarPunto(Expediente $expediente, PuntoRevision $punto, bool $confirmado, ?string $nota, User $actor): void
    {
        $this->escribir($expediente, function (Expediente $e) use ($punto, $confirmado, $nota, $actor) {
            if (! $punto->manual()) {
                throw new TransicionNoPermitida(__('Ese punto lo verifica la plataforma: no se confirma a mano.'));
            }

            if ($e->estado !== EstadoExpediente::EnRevision) {
                throw new TransicionNoPermitida(__('Los puntos de la revisión solo se confirman cuando el expediente está en revisión.'));
            }

            $actual = collect($this->puntos($e))->firstWhere('punto', $punto->value);

            if (! ($actual['requerido'] ?? false)) {
                throw new TransicionNoPermitida(__('Ese punto no se pide en este caso.'));
            }

            ExpedienteRevisionPunto::query()->updateOrCreate(
                ['expediente_id' => $e->id, 'punto' => $punto],
                [
                    'confirmado_por' => $confirmado ? $actor->id : null,
                    'confirmado_en' => $confirmado ? now() : null,
                    'nota' => $nota === '' ? null : $nota,
                ],
            );

            ExpedienteEvento::query()->create([
                'expediente_id' => $e->id,
                'tipo' => TipoEventoExpediente::Punto,
                'actor_id' => $actor->id,
                'motivo' => $nota === '' ? null : $nota,
                'datos' => ['punto' => $punto->value, 'confirmado' => $confirmado],
            ]);
        });
    }

    /**
     * Mueve el expediente por los estados manuales.
     *
     * @throws TransicionNoPermitida
     */
    public function transicion(Expediente $expediente, string $accion, User $actor, ?string $fecha = null, ?string $motivo = null): void
    {
        $this->escribir($expediente, function (Expediente $e) use ($accion, $actor, $fecha, $motivo) {
            [$desde, $hacia] = self::TRANSICIONES[$accion] ?? throw new TransicionNoPermitida(__('Esa acción no existe.'));

            if (! in_array($e->estado, $desde, true)) {
                if ($accion === 'empezar' && $e->estado->derivado()) {
                    $motivos = $this->diagnostico->para($e->user()->withTrashed()->firstOrFail(), $e->tax_year)->motivos();

                    throw new TransicionNoPermitida(__('Todavía no está listo para preparar: :motivos.', ['motivos' => implode('; ', $motivos)]));
                }

                throw new TransicionNoPermitida(__('No puedes hacer eso con el expediente en ":estado".', ['estado' => $e->estado->label()]));
            }

            if ($accion === 'preparado' && ! $this->puedeAprobar($e)) {
                throw new TransicionNoPermitida(__('Faltan puntos de la revisión por cumplir.'));
            }

            $datos = [];

            if ($accion === 'presentado') {
                $dia = $this->fechaDePresentacion($fecha, $e->tax_year);
                $e->update(['presentado_en' => $dia]);
                $datos = ['fecha' => $dia->toDateString()];
            }

            if ($accion === 'rechazado' && trim((string) $motivo) === '') {
                throw new TransicionNoPermitida(__('Escribe el motivo del rechazo.'));
            }

            $this->estados->registrarCambio($e, $hacia, $actor, TipoEventoExpediente::Estado, $accion === 'rechazado' ? $motivo : null, $datos);
        });
    }

    /**
     * Cada escritura: recalcula, se bloquea la fila y todo va en una transacción.
     *
     * @param  callable(Expediente): void  $accion
     */
    private function escribir(Expediente $expediente, callable $accion): void
    {
        DB::transaction(function () use ($expediente, $accion) {
            $cliente = User::withTrashed()->findOrFail($expediente->user_id);
            $this->estados->recalcular($cliente, $expediente->tax_year);

            $actual = Expediente::query()->whereKey($expediente->id)->lockForUpdate()->firstOrFail();
            $accion($actual);

            $expediente->refresh();
        });
    }

    private function exigirDecisionesAbiertas(Expediente $expediente): void
    {
        if ($expediente->estado->trasPresentar()) {
            throw new TransicionNoPermitida(__('Después de presentar la declaración ya no se pueden cambiar las decisiones.'));
        }
    }

    /**
     * Deja el evento de la decisión y, si ya estaba preparado, devuelve el
     * expediente a revisión: hay que corregir TaxWise.
     *
     * @param  array<string, mixed>  $datos
     */
    private function eventoDeDecision(Expediente $expediente, User $actor, string $motivo, array $datos): void
    {
        ExpedienteEvento::query()->create([
            'expediente_id' => $expediente->id,
            'tipo' => TipoEventoExpediente::Decision,
            'actor_id' => $actor->id,
            'motivo' => $motivo,
            'datos' => $datos,
        ]);

        if (in_array($expediente->estado, [EstadoExpediente::PreparadoEnTaxwise, EstadoExpediente::EsperandoFirma], true)) {
            $this->estados->registrarCambio($expediente, EstadoExpediente::EnRevision, $actor, TipoEventoExpediente::Reabierto, __('Cambió una decisión después de preparar'));
        }
    }

    private function fechaDePresentacion(?string $fecha, int $taxYear): Carbon
    {
        if ($fecha === null || $fecha === '') {
            throw new TransicionNoPermitida(__('Escribe la fecha en que se presentó la declaración.'));
        }

        $dia = Carbon::parse($fecha)->startOfDay();

        if ($dia->isFuture()) {
            throw new TransicionNoPermitida(__('La fecha de presentación no puede ser futura.'));
        }

        if ($dia->year <= $taxYear) {
            throw new TransicionNoPermitida(__('La declaración de :anio se presenta en :siguiente o después.', ['anio' => $taxYear, 'siguiente' => $taxYear + 1]));
        }

        return $dia;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function acciones(EstadoExpediente $estado, bool $puedeAprobar, int $faltan): array
    {
        $accion = fn (string $clave, string $etiqueta, ?string $requiere = null, bool $principal = false, ?string $deshabilitada = null) => [
            'accion' => $clave,
            'etiqueta' => $etiqueta,
            'requiere' => $requiere,
            'habilitada' => $deshabilitada === null,
            'motivo_deshabilitada' => $deshabilitada,
            'principal' => $principal,
        ];

        return match ($estado) {
            EstadoExpediente::ListoParaPreparar => [$accion('empezar', __('Empezar la revisión'), principal: true)],
            EstadoExpediente::EnRevision => [$accion(
                'preparado',
                __('Aprobar y marcar preparado en TaxWise'),
                principal: true,
                deshabilitada: $puedeAprobar ? null : ($faltan === 1 ? __('Falta 1 punto de la revisión') : __('Faltan :count puntos de la revisión', ['count' => $faltan])),
            )],
            EstadoExpediente::PreparadoEnTaxwise => [
                $accion('a_firma', __('Enviado a firmar (8879)')),
                $accion('presentado', __('Presentado al IRS'), 'fecha', true),
            ],
            EstadoExpediente::EsperandoFirma => [$accion('presentado', __('Presentado al IRS'), 'fecha', true)],
            EstadoExpediente::Presentado => [
                $accion('aceptado', __('Aceptado por el IRS')),
                $accion('rechazado', __('Rechazado por el IRS'), 'motivo'),
            ],
            EstadoExpediente::Rechazado => [$accion('volver_a_revision', __('Volver a revisión'))],
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function puntosCon(Expediente $expediente, Diagnostico $diagnostico, ?Entrevista $entrevista): array
    {
        $guardados = $expediente->puntos()->with('confirmador:id,name')->get()->keyBy(fn (ExpedienteRevisionPunto $p) => $p->punto->value);
        $personas = $entrevista === null ? collect() : $entrevista->personas()->orderBy('orden')->get();
        $clasificadas = $expediente->personas()->pluck('persona_id')->all();
        $sinClasificar = $personas->reject(fn (EntrevistaPersona $p) => in_array($p->id, $clasificadas, true));

        $tieneAnterior = $entrevista !== null
            && $entrevista->respuestas()->where('pregunta_codigo', 'G5')->whereNull('persona_id')->first()?->valor === 'si';
        $conCreditos = ! empty($expediente->creditos_8867);
        $enRevision = $expediente->estado === EstadoExpediente::EnRevision;

        $situacion = [
            'identidad' => [true, null, null],
            'estado_civil' => [true, $expediente->estado_civil_irs !== null, __('Falta decidir el estado civil para el IRS')],
            'dependientes' => [$personas->isNotEmpty(), $sinClasificar->isEmpty(), $this->detalleSinClasificar($sinClasificar->pluck('orden')->all())],
            'documentos' => [true, $diagnostico->r02Cumple, $this->detalleDocumentos($diagnostico)],
            'excepciones' => [true, $diagnostico->excepcionesQueFrenan === 0, $this->detalleExcepciones($entrevista)],
            'debida_diligencia' => [$conCreditos, null, null],
            'anio_anterior' => [$tieneAnterior, null, null],
        ];

        return array_map(function (PuntoRevision $punto) use ($situacion, $guardados, $enRevision) {
            [$requerido, $cumpleAuto, $detalle] = $situacion[$punto->value];
            $guardado = $guardados->get($punto->value);
            $confirmado = $guardado?->confirmado_en !== null;
            $cumplido = $punto->manual() ? $confirmado : (! $requerido || $cumpleAuto === true);

            return [
                'punto' => $punto->value,
                'titulo' => $punto->titulo(),
                'ayuda' => $punto->ayuda(),
                'manual' => $punto->manual(),
                'requerido' => $requerido,
                'cumplido' => $cumplido,
                'detalle' => $requerido && ! $cumplido ? $detalle : null,
                'confirmado_por' => $confirmado ? $guardado->confirmador?->name : null,
                'confirmado_en' => $confirmado ? $guardado->confirmado_en->toIso8601String() : null,
                'nota' => $guardado?->nota,
                'enlace' => null,
                'editable' => $punto->manual() && $requerido && $enRevision,
            ];
        }, PuntoRevision::cases());
    }

    /**
     * @param  list<array<string, mixed>>  $puntos
     * @param  array<string, string|null>  $enlaces
     * @return list<array<string, mixed>>
     */
    private function conEnlaces(array $puntos, array $enlaces, EstadoExpediente $estado): array
    {
        $destino = [
            PuntoRevision::Identidad->value => 'documentos',
            PuntoRevision::EstadoCivil->value => 'entrevista',
            PuntoRevision::Dependientes->value => 'entrevista',
            PuntoRevision::Documentos->value => 'documentos',
            PuntoRevision::Excepciones->value => 'excepciones',
            PuntoRevision::DebidaDiligencia->value => 'debida_diligencia',
            PuntoRevision::AnioAnterior->value => 'documentos',
        ];

        return array_map(fn (array $p) => [...$p, 'enlace' => $enlaces[$destino[$p['punto']]] ?? null], $puntos);
    }

    /**
     * @return array<string, string|null>
     */
    private function enlaces(User $cliente, int $taxYear): array
    {
        $ruta = fn (string $nombre) => Route::has($nombre) ? route($nombre, ['cliente' => $cliente->id, 'tax_year' => $taxYear]) : null;

        return [
            'documentos' => $ruta('clientes.ficha.documentos'),
            'entrevista' => $ruta('clientes.ficha.entrevista'),
            'excepciones' => $ruta('clientes.ficha.excepciones'),
            'debida_diligencia' => $ruta('clientes.debida-diligencia.show'),
            'hoja' => $ruta('clientes.hoja-taxwise.show'),
        ];
    }

    /**
     * @param  list<int>  $ordenes
     */
    private function detalleSinClasificar(array $ordenes): ?string
    {
        return match (count($ordenes)) {
            0 => null,
            1 => __('Falta clasificar a la persona :orden', ['orden' => $ordenes[0]]),
            default => __('Faltan por clasificar las personas :ordenes', ['ordenes' => implode(', ', $ordenes)]),
        };
    }

    private function detalleDocumentos(Diagnostico $diagnostico): ?string
    {
        $total = count($diagnostico->faltan);

        return match (true) {
            $diagnostico->r02Cumple => null,
            $total === 1 => __('R-02: 1 documento sin validar'),
            default => __('R-02: :count documentos sin validar', ['count' => $total]),
        };
    }

    /**
     * Las frases salen de ExplicadorExcepcion (nunca montos ni SSN).
     */
    private function detalleExcepciones(?Entrevista $entrevista): ?string
    {
        if ($entrevista === null) {
            return null;
        }

        $frenan = Excepcion::query()->where('entrevista_id', $entrevista->id)->abiertas()
            ->whereIn('severidad', [SeveridadRegla::Bloqueante, SeveridadRegla::Revision])
            ->with('entrevista')->orderBy('id')->get();

        if ($frenan->isEmpty()) {
            return null;
        }

        $frases = $frenan->take(3)->map(fn (Excepcion $e) => "{$e->regla_codigo}: {$this->explicador->explicar($e)['caso']}")->all();
        $resto = $frenan->count() - count($frases);

        return implode(' ', $frases).($resto > 0 ? ' '.__('Y :count más.', ['count' => $resto]) : '');
    }

    /**
     * @param  array<string, mixed>  $respuestas  "codigo" o "codigo#persona" → valor
     * @param  Collection<string, array<string, mixed>>  $preguntas
     * @return list<array{codigo: string, texto: string, valor_texto: string}>
     */
    private function respuestasEn(array $codigos, ?int $personaId, array $respuestas, $preguntas, int $taxYear, bool $ingles): array
    {
        $lista = [];

        foreach ($codigos as $codigo) {
            $pregunta = $preguntas->get($codigo);
            $valor = $respuestas[MotorEntrevista::clave($codigo, $personaId)] ?? null;

            // Visible y respondida; y jamás una pregunta sensible.
            if ($pregunta === null || ! empty($pregunta['sensible']) || $valor === null) {
                continue;
            }

            $texto = TextoRespuesta::de($pregunta, $valor, $ingles);

            if ($texto === null) {
                continue;
            }

            $lista[] = [
                'codigo' => $codigo,
                'texto' => str_replace('{anio}', (string) $taxYear, ($ingles && ! empty($pregunta['texto_en'])) ? $pregunta['texto_en'] : (string) $pregunta['texto_es']),
                'valor_texto' => $texto,
            ];
        }

        return $lista;
    }

    /**
     * @param  array<string, mixed>  $respuestas
     * @param  Collection<string, array<string, mixed>>  $preguntas
     * @return list<array<string, mixed>>
     */
    private function personas(Expediente $expediente, ?Entrevista $entrevista, array $respuestas, $preguntas, int $taxYear, bool $ingles): array
    {
        if ($entrevista === null) {
            return [];
        }

        $decisiones = $expediente->personas()->with('decisor:id,name')->get()->keyBy('persona_id');

        return $entrevista->personas()->orderBy('orden')->get()->map(function (EntrevistaPersona $persona) use ($decisiones, $respuestas, $preguntas, $taxYear, $ingles) {
            $decision = $decisiones->get($persona->id);
            $exenta = $this->motivosDeExencion($persona, $respuestas);
            $parentesco = TextoRespuesta::de($preguntas->get('C3') ?? [], $respuestas["C3#{$persona->id}"] ?? null, $ingles);

            return [
                'id' => $persona->id,
                'orden' => $persona->orden,
                // La exenta no tiene más datos: se reconoce por su parentesco.
                'etiqueta' => $exenta !== [] && $parentesco !== null ? "{$persona->etiqueta} · ".mb_strtolower($parentesco) : $persona->etiqueta,
                'clasificacion' => $decision?->clasificacion->value,
                'motivo' => $decision?->motivo,
                'por' => $decision?->decisor?->name,
                'en' => $decision?->decidido_en->toIso8601String(),
                // Quien sus respuestas dejan fuera llega sugerida; el preparador confirma.
                'sugerida' => $exenta === [] ? null : [
                    'clasificacion' => ClasificacionPersona::NoDependiente->value,
                    'motivo' => __('Sus respuestas la dejan fuera como dependiente (:preguntas)', ['preguntas' => implode(', ', $exenta)]),
                ],
                'respuestas' => $this->respuestasEn(self::RESPUESTAS_PERSONA, $persona->id, $respuestas, $preguntas, $taxYear, $ingles),
            ];
        })->all();
    }

    /**
     * Las preguntas que dejan a la persona fuera como dependiente: C12 = sí,
     * C13 = no o C14 = no. Vacío si no está exenta.
     *
     * @param  array<string, mixed>  $respuestas
     * @return list<string>
     */
    private function motivosDeExencion(EntrevistaPersona $persona, array $respuestas): array
    {
        $dice = fn (string $codigo, string $valor) => ($respuestas["{$codigo}#{$persona->id}"] ?? null) === $valor;

        return array_values(array_filter([
            $dice('C12', 'si') ? 'C12' : null,
            $dice('C13', 'no') ? 'C13' : null,
            $dice('C14', 'no') ? 'C14' : null,
        ]));
    }
}
