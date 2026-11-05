<?php

namespace App\Services\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\OrigenDato;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\Expediente\Expediente;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\SituacionEntrevista;
use App\Services\Lectura\CalculadoraConfianza;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\ExplicadorExcepcion;
use App\Services\Lectura\Reglas\R01Ssn;
use App\Support\ParametrosFiscales;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * La hoja de trabajo para TaxWise (F5.5): lo que el preparador digita, en el
 * orden de carga, con el valor, a qué línea va, de dónde salió y en qué estado
 * está cada dato, más los totales de control por línea. Es una sola fuente para
 * la pantalla, la impresión y el CSV. No calcula nada fiscal: las líneas de
 * cálculo las hace TaxWise. Lo sensible nunca sale en claro (`valor` nulo y su
 * `mascara`); se revela con los endpoints del visor y de la entrevista, que
 * piden contraseña y dejan registro.
 */
class HojaTaxwise
{
    /**
     * El orden de carga en una sola fuente (P6): se ajusta aquí cuando GTS
     * muestre las pantallas de TaxWise. `totales` va al final, aparte.
     */
    public const SECCIONES = [
        'encabezado' => ['preguntas' => ['A1', 'A1.1', 'A1.2', 'A2', 'A3', 'A12', 'B1', 'B4', 'B4.1', 'B4.2', 'B4.3', 'A5', 'A9', 'A14', 'A15'], 'grupos' => []],
        'dependientes' => ['preguntas' => ['C1', 'C2', 'C3', 'C4'], 'grupos' => []],
        'salarios' => ['preguntas' => [], 'grupos' => ['trabajo']],
        'negocio' => ['preguntas' => [], 'grupos' => ['negocio']],
        'otros_ingresos' => ['preguntas' => [], 'grupos' => ['jubilacion', 'inversiones', 'otros_ingresos']],
        'deducciones' => ['preguntas' => [], 'grupos' => ['gastos']],
        'situaciones' => ['preguntas' => [], 'grupos' => ['situaciones']],
        'pagos' => ['preguntas' => ['G1', 'G2'], 'grupos' => ['pagos'], 'sin' => ['datos-bancarios']],
        'banco' => ['preguntas' => ['G3', 'G4'], 'documentos' => ['datos-bancarios']],
    ];

    /** Los documentos que el cliente agrega por fuera de la lista caen en esta sección. */
    private const SECCION_DE_OTROS = 'situaciones';

    /** Casillas con el nombre de quien emite el documento (solo salen si no son sensibles). */
    private const CASILLAS_EMISOR = ['c', 'pagador-nombre'];

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly MotorEntrevista $motor,
        private readonly DatosDocumento $datos,
        private readonly ExplicadorExcepcion $explicador,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function para(User $cliente, int $taxYear): array
    {
        $hoja = [
            'tax_year' => $taxYear,
            'generado_en' => now()->toIso8601String(),
            'conteos' => ['filas' => 0, 'pendientes' => 0, 'baja_confianza' => 0, 'con_excepcion' => 0, 'sin_validar' => 0],
            'secciones' => [],
            'totales' => [],
            'confianza_tabla' => $this->tablaConfianza(),
            'umbral' => (float) (ParametrosFiscales::valor($taxYear, 'lectura', 'umbral_confianza') ?? CalculadoraConfianza::CLARO),
        ];

        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();

        if ($entrevista === null) {
            return $hoja;
        }

        $ctx = $this->contexto($cliente, $entrevista, $hoja['umbral']);

        foreach (self::SECCIONES as $codigo => $definicion) {
            $grupos = $codigo === 'dependientes' ? $this->gruposDependientes($ctx) : $this->gruposDePreguntas($ctx, $codigo, $definicion['preguntas']);

            foreach ($this->itemsDeSeccion($ctx, $codigo, $definicion) as $item) {
                $grupos[] = $this->grupoDeItem($ctx, $item);
            }

            if ($grupos !== []) {
                $hoja['secciones'][] = ['codigo' => $codigo, 'titulo' => $this->tituloSeccion($codigo), 'grupos' => $grupos];
            }
        }

        $hoja['conteos'] = $this->conteos($hoja['secciones']);
        $hoja['totales'] = $this->totales($ctx);

        return $hoja;
    }

    /**
     * El CSV: encabezado, una fila por fila de la hoja y los totales. Siempre
     * enmascarado: el valor de lo sensible es su máscara, nunca lo revelado.
     *
     * @return iterable<int, list<string>>
     */
    public function filasCsv(User $cliente, int $taxYear): iterable
    {
        $hoja = $this->para($cliente, $taxYear);

        yield [__('Sección'), __('Formulario y línea'), __('Descripción'), __('Valor'), __('Origen'), __('Estado')];

        foreach ($hoja['secciones'] as $seccion) {
            foreach ($seccion['grupos'] as $grupo) {
                foreach ($grupo['filas'] as $fila) {
                    yield [$seccion['titulo'], (string) $fila['destino'], $fila['descripcion'], (string) ($fila['valor'] ?? $fila['mascara'] ?? ''), $fila['origen'], $fila['estado_texto']];
                }
            }
        }

        foreach ($hoja['totales'] as $total) {
            yield [__('Totales de control'), $total['destino'], $total['descripcion'], (string) ($total['valor'] ?? ''), $total['nota'], $total['completo'] ? __('Completo') : __('Incompleto')];
        }
    }

    // ── Contexto ────────────────────────────────────────────────────────────

    /**
     * Todo lo que las filas necesitan, leído una vez.
     *
     * @return array<string, mixed>
     */
    private function contexto(User $cliente, Entrevista $entrevista, float $umbral): array
    {
        $anio = $entrevista->tax_year;
        $documentos = $this->catalogo->documentos($anio)->keyBy('codigo');
        $items = $entrevista->documentos()->vigentes()->with('revisor:id,name')->withCount('archivos')->orderBy('id')->get();
        $datos = $items->mapWithKeys(fn (EntrevistaDocumento $i) => [$i->id => $this->datos->vigentes($i)]);
        $datosIds = $datos->flatten(1)->pluck('id');

        $excepciones = Excepcion::query()->abiertas()
            ->whereIn('documento_dato_id', $datosIds->all())
            ->with('entrevista')
            ->orderBy('id')
            ->get()
            ->groupBy('documento_dato_id');

        $respuestas = $entrevista->respuestas()->with('actor:id,name')->get()
            ->keyBy(fn (EntrevistaRespuesta $r) => MotorEntrevista::clave($r->pregunta_codigo, $r->persona_id));

        $personasIds = $datos->flatten(1)->pluck('corregido_por')->filter()->unique()->all();
        $expediente = Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $anio)->with(['personas.decisor:id,name', 'decisorEstadoCivil:id,name'])->first();

        [$destinosCasilla, $destinosRespuesta] = $this->destinos($anio);

        return [
            'cliente' => $cliente,
            'anio' => $anio,
            'ingles' => str_starts_with(app()->getLocale(), 'en'),
            'umbral' => $umbral,
            'entrevista' => $entrevista,
            'situacion' => $this->motor->situacion($entrevista),
            'preguntas' => $this->catalogo->preguntas($anio)->keyBy('codigo'),
            'documentos' => $documentos,
            'items' => $items,
            'datos' => $datos,
            'excepciones' => $excepciones,
            'respuestas' => $respuestas,
            'correctores' => User::withTrashed()->whereIn('id', $personasIds)->pluck('name', 'id'),
            'expediente' => $expediente,
            'destinosCasilla' => $destinosCasilla,
            'destinosRespuesta' => $destinosRespuesta,
        ];
    }

    /**
     * A qué línea va cada casilla y cada respuesta, según las líneas del catálogo.
     *
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>}
     */
    private function destinos(int $anio): array
    {
        $casillas = [];
        $respuestas = [];

        foreach ($this->catalogo->formularios($anio) as $formulario) {
            foreach ($formulario['lineas'] ?? [] as $linea) {
                $destino = $formulario['nombre'].' · '.__('línea').' '.$linea['linea'];
                $tipo = $linea['origen_tipo'];

                if (! in_array($tipo, ['casilla', 'respuesta'], true) || empty($linea['origen_clave'])) {
                    continue;
                }

                foreach (explode(';', (string) $linea['origen_clave']) as $clave) {
                    $clave = trim($clave);

                    if ($tipo === 'casilla') {
                        $casillas[$clave][] = $destino;
                    } else {
                        $respuestas[$clave][] = $destino;
                    }
                }
            }
        }

        return [array_map('array_unique', $casillas), array_map('array_unique', $respuestas)];
    }

    // ── Secciones de preguntas ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $ctx
     * @param  list<string>  $codigos
     * @return list<array<string, mixed>>
     */
    private function gruposDePreguntas(array $ctx, string $seccion, array $codigos): array
    {
        $filas = [];

        foreach ($codigos as $codigo) {
            if ($seccion === 'encabezado' && $codigo === 'B1') {
                $filas[] = $this->filaEstadoCivil($ctx);
            }

            $fila = $this->filaPregunta($ctx, $codigo, null, $this->destinoPorDefecto($seccion));

            if ($fila !== null) {
                $filas[] = $fila;
            }

            if ($seccion === 'encabezado' && $codigo === 'A2') {
                $filas[] = $this->filaTarjeta($ctx, $this->itemDe($ctx, 'tarjeta-ssn', null), __('SSN del contribuyente'), 'Form 1040 · '.__('Encabezado'), 'ssn-contribuyente');
            }

            if ($seccion === 'encabezado' && $codigo === 'B4.3') {
                $tarjeta = $this->itemDe($ctx, 'tarjeta-ssn-conyuge', null);

                if ($tarjeta !== null) {
                    $filas[] = $this->filaTarjeta($ctx, $tarjeta, __('SSN del cónyuge'), 'Form 1040 · '.__('Encabezado'), 'ssn-conyuge');
                }
            }
        }

        $filas = array_values(array_filter($filas));

        return $filas === [] ? [] : [['titulo' => $this->tituloSeccion($seccion), 'item_id' => null, 'enlace' => null, 'filas' => $filas]];
    }

    private function destinoPorDefecto(string $seccion): ?string
    {
        return match ($seccion) {
            'encabezado' => 'Form 1040 · '.__('Encabezado'),
            'dependientes' => 'Form 1040 · '.__('Dependientes'),
            default => null,
        };
    }

    /**
     * Una pregunta visible: su respuesta o "pendiente" si falta.
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>|null
     */
    private function filaPregunta(array $ctx, string $codigo, ?int $personaId, ?string $destinoPorDefecto): ?array
    {
        /** @var SituacionEntrevista $situacion */
        $situacion = $ctx['situacion'];
        $pregunta = $ctx['preguntas']->get($codigo);

        if ($pregunta === null || ! $situacion->esVisible($codigo, $personaId)) {
            return null;
        }

        /** @var EntrevistaRespuesta|null $respuesta */
        $respuesta = $ctx['respuestas']->get(MotorEntrevista::clave($codigo, $personaId));
        $sensible = (bool) $pregunta['sensible'];
        $texto = str_replace('{anio}', (string) $ctx['anio'], $ctx['ingles'] && ! empty($pregunta['texto_en']) ? $pregunta['texto_en'] : (string) $pregunta['texto_es']);
        $destino = isset($ctx['destinosRespuesta'][$codigo]) ? implode(' '.__('y').' ', $ctx['destinosRespuesta'][$codigo]) : $destinoPorDefecto;
        $enlace = $this->enlaceEntrevista($ctx);
        $origen = __('Entrevista :codigo', ['codigo' => $codigo]);
        $id = $respuesta === null ? 'pendiente:'.MotorEntrevista::clave($codigo, $personaId) : 'respuesta:'.$respuesta->id;

        if ($respuesta === null) {
            return $this->fila($id, $destino, $texto, null, 'texto', $origen, $enlace, 'pendiente', __('Falta: el cliente no la ha respondido'), ['sensible' => $sensible]);
        }

        if ($sensible) {
            return $this->fila($id, $destino, $texto, null, 'texto', $origen, $enlace, 'respondido', $this->textoRespondido($respuesta), [
                'sensible' => true,
                'mascara' => $this->mascara($codigo === 'B4.3' ? 'ssn' : null, $respuesta->valor),
                'revelar_url' => route('clientes.entrevista.respuestas.revelar', ['cliente' => $ctx['cliente']->id, 'respuesta' => $respuesta->id]),
            ]);
        }

        $valor = TextoRespuesta::de($pregunta, $respuesta->valor, $ctx['ingles']);

        return $this->fila($id, $destino, $texto, $valor, 'texto', $origen, $enlace, 'respondido', $this->textoRespondido($respuesta));
    }

    private function textoRespondido(EntrevistaRespuesta $respuesta): string
    {
        $fecha = $this->fecha($respuesta->respondida_en);

        return match ($respuesta->canal) {
            CanalRespuesta::Whatsapp => __('Respondido por WhatsApp el :fecha', ['fecha' => $fecha]),
            CanalRespuesta::Preparador => __('Escrito por :nombre el :fecha', ['nombre' => $respuesta->actor?->name ?? __('el preparador'), 'fecha' => $fecha]),
            CanalRespuesta::Migracion => __('Migrado de la ficha anterior el :fecha', ['fecha' => $fecha]),
            default => __('Respondido en el portal el :fecha', ['fecha' => $fecha]),
        };
    }

    /**
     * La decisión del preparador sobre el estado civil para el IRS.
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function filaEstadoCivil(array $ctx): array
    {
        /** @var Expediente|null $expediente */
        $expediente = $ctx['expediente'];
        $decidido = $expediente?->estado_civil_irs;
        $enlace = $this->enlaceRevision($ctx);
        $descripcion = __('Estado civil para el IRS');
        $destino = 'Form 1040 · '.__('Estado civil');

        if ($decidido === null) {
            return $this->fila('decision:estado_civil', $destino, $descripcion, null, 'texto', __('Revisión'), $enlace, 'pendiente', __('Falta decidirlo en Revisión'));
        }

        return $this->fila('decision:estado_civil', $destino, $descripcion, __($decidido->label()), 'texto', __('Revisión'), $enlace, 'decidido', $this->textoDecidido($expediente->decisorEstadoCivil?->name, $expediente->estado_civil_en));
    }

    private function textoDecidido(?string $nombre, ?CarbonInterface $fecha): string
    {
        return __('Decidido por :nombre el :fecha', ['nombre' => $nombre ?? __('el preparador'), 'fecha' => $fecha === null ? '—' : $this->fecha($fecha)]);
    }

    // ── Dependientes ────────────────────────────────────────────────────────

    /**
     * Una por persona vigente, sin las exentas (sus respuestas la dejan fuera
     * como dependiente: no tienen más preguntas ni tarjeta).
     *
     * @param  array<string, mixed>  $ctx
     * @return list<array<string, mixed>>
     */
    private function gruposDependientes(array $ctx): array
    {
        /** @var Entrevista $entrevista */
        $entrevista = $ctx['entrevista'];
        /** @var SituacionEntrevista $situacion */
        $situacion = $ctx['situacion'];
        $grupos = [];

        foreach ($entrevista->personas()->orderBy('orden')->get() as $persona) {
            if ($this->esExenta($situacion, $persona)) {
                continue;
            }

            $filas = [];

            foreach (self::SECCIONES['dependientes']['preguntas'] as $codigo) {
                $fila = $this->filaPregunta($ctx, $codigo, $persona->id, 'Form 1040 · '.__('Dependientes'));

                if ($fila !== null) {
                    $filas[] = $fila;
                }
            }

            $filas[] = $this->filaTarjeta($ctx, $this->itemDe($ctx, 'tarjeta-ssn', $persona->id), __('SSN de la persona'), 'Form 1040 · '.__('Dependientes'), 'ssn-persona-'.$persona->id);
            $filas[] = $this->filaClasificacion($ctx, $persona);

            $grupos[] = ['titulo' => $persona->etiqueta, 'item_id' => null, 'enlace' => null, 'filas' => array_values(array_filter($filas))];
        }

        return $grupos;
    }

    private function esExenta(SituacionEntrevista $situacion, EntrevistaPersona $persona): bool
    {
        $r = fn (string $codigo) => $situacion->respuestas[MotorEntrevista::clave($codigo, $persona->id)] ?? null;

        return $r('C12') === 'si' || $r('C13') === 'no' || $r('C14') === 'no';
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function filaClasificacion(array $ctx, EntrevistaPersona $persona): array
    {
        /** @var Expediente|null $expediente */
        $expediente = $ctx['expediente'];
        $decision = $expediente?->personas->firstWhere('persona_id', $persona->id);
        $enlace = $this->enlaceRevision($ctx);
        $descripcion = __('Clasificación');
        $destino = 'Form 1040 · '.__('Dependientes');
        $id = 'decision:persona:'.$persona->id;

        if ($decision === null) {
            return $this->fila($id, $destino, $descripcion, null, 'texto', __('Revisión'), $enlace, 'pendiente', __('Falta decidirlo en Revisión'));
        }

        return $this->fila($id, $destino, $descripcion, __($decision->clasificacion->label()), 'texto', __('Revisión'), $enlace, 'decidido', $this->textoDecidido($decision->decisor?->name, $decision->decidido_en));
    }

    // ── Documentos ──────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function itemDe(array $ctx, string $codigo, ?int $personaId): ?EntrevistaDocumento
    {
        return $ctx['items']->first(fn (EntrevistaDocumento $i) => $i->documento_codigo === $codigo
            && $i->persona_id === $personaId
            && $i->estado !== EstadoDocumento::NoAplica);
    }

    /**
     * Los ítems de una sección, en el orden del catálogo.
     *
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $definicion
     * @return Collection<int, EntrevistaDocumento>
     */
    private function itemsDeSeccion(array $ctx, string $codigo, array $definicion): Collection
    {
        $grupos = $definicion['grupos'] ?? [];
        $sin = $definicion['sin'] ?? [];
        $propios = $definicion['documentos'] ?? [];

        return $ctx['items']
            ->filter(function (EntrevistaDocumento $item) use ($ctx, $codigo, $grupos, $sin, $propios) {
                if ($item->estado === EstadoDocumento::NoAplica) {
                    return false;
                }

                if ($item->esOtro()) {
                    return $codigo === self::SECCION_DE_OTROS;
                }

                $grupo = $ctx['documentos']->get($item->documento_codigo)['grupo'] ?? null;

                return in_array($item->documento_codigo, $propios, true)
                    || (in_array($grupo, $grupos, true) && ! in_array($item->documento_codigo, $sin, true));
            })
            ->sortBy(fn (EntrevistaDocumento $item) => [
                array_search($ctx['documentos']->get($item->documento_codigo)['grupo'] ?? '', $grupos, true) === false ? 99 : array_search($ctx['documentos']->get($item->documento_codigo)['grupo'] ?? '', $grupos, true),
                $ctx['documentos']->get($item->documento_codigo)['orden'] ?? 999,
                $item->persona_id ?? 0,
                $item->numero,
                $item->id,
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function grupoDeItem(array $ctx, EntrevistaDocumento $item): array
    {
        $titulo = $this->tituloItem($ctx, $item);

        return [
            'titulo' => $titulo,
            'item_id' => $item->id,
            'enlace' => $this->enlaceVisor($ctx, $item),
            'filas' => $this->filasDeItem($ctx, $item, $titulo),
        ];
    }

    /**
     * "W-2 #1 (Empresa X)": el nombre oficial, el número si hay más de uno y el
     * emisor si su casilla no es sensible.
     *
     * @param  array<string, mixed>  $ctx
     */
    private function tituloItem(array $ctx, EntrevistaDocumento $item, bool $conEmisor = true): string
    {
        $documento = $ctx['documentos']->get($item->documento_codigo);

        $nombre = $item->esOtro()
            ? ($item->descripcion ?: __('Otro documento'))
            : (string) ($documento['nombre_oficial'] ?? ($ctx['ingles'] ? ($documento['nombre_sencillo_en'] ?? $documento['nombre_sencillo_es'] ?? null) : ($documento['nombre_sencillo_es'] ?? null)) ?? $item->documento_codigo);

        $iguales = $ctx['items']->filter(fn (EntrevistaDocumento $i) => $i->documento_codigo === $item->documento_codigo && $i->persona_id === $item->persona_id)->count();
        $titulo = $iguales > 1 || $item->numero > 1 ? "{$nombre} #{$item->numero}" : $nombre;

        $emisor = $conEmisor ? $this->emisor($ctx, $item, $documento) : null;

        return $emisor === null ? $titulo : "{$titulo} ({$emisor})";
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>|null  $documento
     */
    private function emisor(array $ctx, EntrevistaDocumento $item, ?array $documento): ?string
    {
        foreach ($documento['casillas'] ?? [] as $casilla) {
            if (! in_array($casilla['casilla'], self::CASILLAS_EMISOR, true) || ($casilla['sensible'] ?? false)) {
                continue;
            }

            $valor = $ctx['datos'][$item->id]->first(fn (DocumentoDato $d) => $d->casilla === (string) $casilla['casilla'] && is_scalar($d->valor) && trim((string) $d->valor) !== '')?->valor;

            if ($valor !== null) {
                return trim((string) $valor);
            }
        }

        return null;
    }

    /**
     * Las filas de un documento: una por casilla con valor y una por casilla
     * clave sin valor; el que no se lee sale como "digitar desde el documento".
     *
     * @param  array<string, mixed>  $ctx
     * @return list<array<string, mixed>>
     */
    private function filasDeItem(array $ctx, EntrevistaDocumento $item, string $titulo): array
    {
        $documento = $ctx['documentos']->get($item->documento_codigo);
        $casillas = collect($documento['casillas'] ?? [])->values();
        $enlace = $this->enlaceVisor($ctx, $item);
        $nombre = $this->tituloItem($ctx, $item, false);

        if ($item->esOtro() || $casillas->isEmpty()) {
            if ($item->estado === EstadoDocumento::Pendiente) {
                return [$this->fila("item:{$item->id}", null, $nombre, null, 'texto', $titulo, $enlace, 'pendiente', __('Falta: el cliente no lo ha subido'))];
            }

            return [$this->fila("item:{$item->id}", null, $nombre, null, 'texto', $this->origenManual($titulo, $item->archivos_count), $enlace, 'manual', __('Digitar desde el documento'))];
        }

        /** @var Collection<int, DocumentoDato> $datos */
        $datos = $ctx['datos'][$item->id];

        if ($datos->isEmpty()) {
            return [$this->fila("item:{$item->id}", null, $nombre, null, 'texto', $titulo, $enlace, 'pendiente', $this->textoSinLeer($item))];
        }

        $filas = [];
        $conocidas = [];

        foreach ($casillas as $casilla) {
            $conocidas[] = (string) $casilla['casilla'];
            $delaCasilla = $datos->filter(fn (DocumentoDato $d) => $d->casilla === (string) $casilla['casilla']);

            if ($delaCasilla->isEmpty()) {
                if ($casilla['clave'] ?? false) {
                    $filas[] = $this->filaSinValor($ctx, $item, $casilla, $titulo, "item:{$item->id}:{$casilla['casilla']}");
                }

                continue;
            }

            foreach ($delaCasilla as $dato) {
                if ($this->vacio($dato->valor) && ! ($casilla['clave'] ?? false)) {
                    continue;
                }

                $filas[] = $this->filaDato($ctx, $item, $dato, $casilla, $titulo);
            }
        }

        // Una casilla que el catálogo ya no conoce se trata como sensible.
        foreach ($datos->reject(fn (DocumentoDato $d) => in_array($d->casilla, $conocidas, true) || $this->vacio($d->valor)) as $dato) {
            $filas[] = $this->filaDato($ctx, $item, $dato, ['casilla' => $dato->casilla, 'descripcion' => $dato->casilla, 'tipo_dato' => null, 'clave' => false, 'sensible' => true], $titulo);
        }

        return $filas;
    }

    private function origenManual(string $titulo, int $archivos): string
    {
        return $archivos === 1
            ? __(':titulo, 1 archivo', ['titulo' => $titulo])
            : __(':titulo, :n archivos', ['titulo' => $titulo, 'n' => $archivos]);
    }

    private function textoSinLeer(EntrevistaDocumento $item): string
    {
        return $item->estado === EstadoDocumento::Pendiente || $item->archivos_count === 0
            ? __('Falta: el cliente no lo ha subido')
            : __('Falta leerlo');
    }

    /**
     * La fila de una casilla de la tarjeta del Seguro Social (SSN de una persona).
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function filaTarjeta(array $ctx, ?EntrevistaDocumento $item, string $descripcion, string $destino, string $clave): array
    {
        if ($item === null) {
            return $this->fila("tarjeta:{$clave}", $destino, $descripcion, null, 'ssn', __('Tarjeta del Seguro Social'), null, 'pendiente', __('Falta: el cliente no lo ha subido'), ['sensible' => true]);
        }

        $titulo = $this->tituloItem($ctx, $item, false);
        $enlace = $this->enlaceVisor($ctx, $item);
        $casilla = collect($ctx['documentos']->get($item->documento_codigo)['casillas'] ?? [])->firstWhere('casilla', 'ssn')
            ?? ['casilla' => 'ssn', 'descripcion' => $descripcion, 'tipo_dato' => 'ssn', 'clave' => true, 'sensible' => true];
        $dato = $ctx['datos'][$item->id]->first(fn (DocumentoDato $d) => $d->casilla === 'ssn');

        if ($dato === null) {
            return $this->fila("tarjeta:{$clave}", $destino, $descripcion, null, 'ssn', $titulo, $enlace, 'pendiente', $this->textoSinLeer($item), ['sensible' => true]);
        }

        return $this->filaDato($ctx, $item, $dato, $casilla, $titulo, ['descripcion' => $descripcion, 'destino' => $destino]);
    }

    /**
     * Una casilla clave sin valor.
     *
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $casilla
     * @return array<string, mixed>
     */
    private function filaSinValor(array $ctx, EntrevistaDocumento $item, array $casilla, string $titulo, string $id): array
    {
        return $this->fila($id, $this->destinoDeCasilla($ctx, $item, $casilla), $this->descripcionCasilla($casilla), null, $casilla['tipo_dato'] ?? 'texto', $this->origenCasilla($titulo, $casilla), $this->enlaceVisor($ctx, $item), 'pendiente', __('Sin valor en el documento'), [
            'sensible' => (bool) ($casilla['sensible'] ?? false),
        ]);
    }

    /**
     * La fila de un dato leído, con su estado, su confianza explicada y, si es
     * sensible, solo su máscara y el endpoint para revelarlo.
     *
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $casilla
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function filaDato(array $ctx, EntrevistaDocumento $item, DocumentoDato $dato, array $casilla, string $titulo, array $cambios = []): array
    {
        $sensible = (bool) ($casilla['sensible'] ?? false);
        $tipo = $casilla['tipo_dato'] ?? null;
        [$estado, $texto] = $this->estadoDelDato($ctx, $item, $dato, $casilla);
        $vacio = $this->vacio($dato->valor);
        $enlace = $this->enlaceVisor($ctx, $item, $dato->id);

        $fila = $this->fila(
            'dato:'.$dato->id,
            $cambios['destino'] ?? $this->destinoDeCasilla($ctx, $item, $casilla),
            $cambios['descripcion'] ?? $this->descripcionCasilla($casilla),
            $sensible || $vacio ? null : (string) $dato->valor,
            $tipo ?? 'texto',
            $this->origenCasilla($titulo, $casilla),
            $enlace,
            $estado,
            $texto,
            [
                'sensible' => $sensible,
                'mascara' => $sensible && ! $vacio ? $this->mascara($tipo, $dato->valor) : null,
                'revelar_url' => $sensible && ! $vacio ? route('clientes.documentos.visor.revelar', ['cliente' => $ctx['cliente']->id, 'item' => $item->id, 'dato' => $dato->id]) : null,
                'confianza' => $dato->confianza === null ? null : [
                    'valor' => $dato->confianza,
                    'titulo' => $dato->motivo_confianza === null ? null : __($dato->motivo_confianza->titulo()),
                    'explicacion' => $dato->motivo_confianza === null ? null : __($dato->motivo_confianza->explicacion()),
                ],
            ],
        );

        return $fila;
    }

    /**
     * El estado de un dato, por prioridad: excepción abierta, pendiente, baja
     * confianza, corregido, validado, sin validar.
     *
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $casilla
     * @return array{0: string, 1: string}
     */
    private function estadoDelDato(array $ctx, EntrevistaDocumento $item, DocumentoDato $dato, array $casilla): array
    {
        /** @var Excepcion|null $excepcion */
        $excepcion = ($ctx['excepciones'][$dato->id] ?? collect())->first();

        if ($excepcion !== null) {
            $severidad = match ($excepcion->severidad) {
                SeveridadRegla::Bloqueante => __('bloqueante'),
                SeveridadRegla::Revision => __('de revisión'),
                SeveridadRegla::Informativa => __('informativa'),
            };

            return ['con_excepcion', __('Regla :regla abierta (:severidad)', ['regla' => $excepcion->regla_codigo, 'severidad' => $severidad]).': '.$this->explicador->explicar($excepcion)['caso']];
        }

        if ($this->vacio($dato->valor)) {
            return ['pendiente', __('Sin valor en el documento')];
        }

        if ($dato->confianza !== null && $dato->confianza < $ctx['umbral'] && $dato->origen === OrigenDato::Lector) {
            return ['baja_confianza', __('Leído con dudas (:confianza)', ['confianza' => $this->numero($dato->confianza)])];
        }

        if ($dato->origen === OrigenDato::Preparador) {
            return ['corregido', __('Corregido por :nombre el :fecha', [
                'nombre' => $ctx['correctores']->get($dato->corregido_por) ?? __('el preparador'),
                'fecha' => $dato->corregido_en === null ? '—' : $this->fecha($dato->corregido_en),
            ])];
        }

        if ($item->estado === EstadoDocumento::Validado) {
            return ['validado', $item->revisado_en === null
                ? __('Validado por :nombre', ['nombre' => $item->revisor?->name ?? __('el preparador')])
                : __('Validado por :nombre el :fecha', ['nombre' => $item->revisor?->name ?? __('el preparador'), 'fecha' => $this->fecha($item->revisado_en)])];
        }

        return ['sin_validar', __('Leído, sin validar')];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $casilla
     */
    private function destinoDeCasilla(array $ctx, EntrevistaDocumento $item, array $casilla): ?string
    {
        $destinos = $ctx['destinosCasilla']["{$item->documento_codigo}:{$casilla['casilla']}"] ?? [];

        return $destinos === [] ? null : implode(' '.__('y').' ', $destinos);
    }

    /**
     * @param  array<string, mixed>  $casilla
     */
    private function descripcionCasilla(array $casilla): string
    {
        return __('Casilla :casilla', ['casilla' => $casilla['casilla']]).' — '.$casilla['descripcion'];
    }

    /**
     * @param  array<string, mixed>  $casilla
     */
    private function origenCasilla(string $titulo, array $casilla): string
    {
        return $titulo.', '.__('casilla :casilla', ['casilla' => $casilla['casilla']]);
    }

    // ── Totales de control ──────────────────────────────────────────────────

    /**
     * Por cada línea de casilla y tipo monto de los formularios del cliente: la
     * suma de esas casillas en todos sus documentos. Es una suma, no un cálculo.
     *
     * @param  array<string, mixed>  $ctx
     * @return list<array<string, mixed>>
     */
    private function totales(array $ctx): array
    {
        /** @var SituacionEntrevista $situacion */
        $situacion = $ctx['situacion'];
        $activos = array_column($situacion->formularios, 'codigo');
        $totales = [];

        foreach ($this->catalogo->formularios($ctx['anio']) as $formulario) {
            if (! in_array($formulario['codigo'], $activos, true)) {
                continue;
            }

            foreach ($formulario['lineas'] ?? [] as $linea) {
                if ($linea['origen_tipo'] !== 'casilla' || empty($linea['origen_clave'])) {
                    continue;
                }

                $total = $this->totalDeLinea($ctx, (string) $linea['origen_clave']);

                if ($total === null) {
                    continue;
                }

                $totales[] = [
                    'destino' => $formulario['nombre'].' · '.__('línea').' '.$linea['linea'],
                    'descripcion' => $linea['descripcion'],
                    'valor' => $total['valor'],
                    'documentos' => $total['documentos'],
                    'completo' => $total['completo'],
                    'nota' => match (true) {
                        $total['valor'] === null => __('Ningún documento tiene valor'),
                        $total['documentos'] === 1 => __('Suma de 1 documento'),
                        default => __('Suma de :n documentos', ['n' => $total['documentos']]),
                    },
                ];
            }
        }

        return $totales;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{valor: string|null, documentos: int, completo: bool}|null null si la línea no tiene casillas de monto o el cliente no tiene esos documentos
     */
    private function totalDeLinea(array $ctx, string $origenClave): ?array
    {
        $suma = null;
        $documentos = 0;
        $completo = true;
        $hayMonto = false;
        $hayItems = false;

        foreach (explode(';', $origenClave) as $token) {
            [$codigo, $casillaCodigo] = array_pad(explode(':', trim($token), 2), 2, null);
            $casilla = collect($ctx['documentos']->get($codigo)['casillas'] ?? [])->firstWhere('casilla', $casillaCodigo);

            if ($casilla === null || ($casilla['tipo_dato'] ?? null) !== 'monto' || ($casilla['sensible'] ?? false)) {
                continue;
            }

            $hayMonto = true;

            foreach ($ctx['items']->where('documento_codigo', $codigo) as $item) {
                if ($item->estado === EstadoDocumento::NoAplica) {
                    continue;
                }

                $hayItems = true;
                $datos = $ctx['datos'][$item->id];
                $dato = $datos->first(fn (DocumentoDato $d) => $d->casilla === $casillaCodigo && ! $this->vacio($d->valor))
                    ?? $datos->first(fn (DocumentoDato $d) => $d->casilla === $casillaCodigo);

                if ($dato === null) {
                    // Sin lectura (falta subirlo o leerlo) o sin esa casilla en la lectura.
                    $completo = $completo && $datos->isNotEmpty() && ! ($casilla['clave'] ?? false);

                    continue;
                }

                [$estado] = $this->estadoDelDato($ctx, $item, $dato, $casilla);
                $completo = $completo && in_array($estado, ['validado', 'corregido'], true);

                if (! $this->vacio($dato->valor) && is_numeric($dato->valor)) {
                    $suma = bcadd($suma ?? '0', number_format((float) $dato->valor, 2, '.', ''), 2);
                    $documentos++;
                }
            }
        }

        // Sin casillas de monto, o sin ningún documento del cliente que las traiga, la línea no aplica.
        if (! $hayMonto || ! $hayItems) {
            return null;
        }

        return ['valor' => $suma, 'documentos' => $documentos, 'completo' => $completo && $suma !== null];
    }

    // ── Conteos, enlaces y textos ───────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $secciones
     * @return array<string, int>
     */
    private function conteos(array $secciones): array
    {
        $filas = collect($secciones)->flatMap(fn (array $s) => $s['grupos'])->flatMap(fn (array $g) => $g['filas']);

        return [
            'filas' => $filas->count(),
            'pendientes' => $filas->where('estado', 'pendiente')->count(),
            'baja_confianza' => $filas->where('estado', 'baja_confianza')->count(),
            'con_excepcion' => $filas->where('estado', 'con_excepcion')->count(),
            'sin_validar' => $filas->where('estado', 'sin_validar')->count(),
        ];
    }

    /**
     * @return list<array{motivo: string, confianza: float, titulo: string, explicacion: string}>
     */
    private function tablaConfianza(): array
    {
        return array_map(fn (array $fila) => [
            ...$fila,
            'titulo' => __($fila['titulo']),
            'explicacion' => __($fila['explicacion']),
        ], CalculadoraConfianza::tabla());
    }

    private function tituloSeccion(string $codigo): string
    {
        return match ($codigo) {
            'encabezado' => __('Encabezado y estado civil'),
            'dependientes' => __('Dependientes'),
            'salarios' => __('W-2'),
            'negocio' => __('Negocio (1099, K-1 y registros)'),
            'otros_ingresos' => __('Otros ingresos'),
            'deducciones' => __('Deducciones y gastos'),
            'situaciones' => __('Situaciones especiales'),
            'pagos' => __('Pagos'),
            'banco' => __('Datos bancarios'),
            default => $codigo,
        };
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function enlaceVisor(array $ctx, EntrevistaDocumento $item, ?int $datoId = null): string
    {
        $parametros = ['cliente' => $ctx['cliente']->id, 'item' => $item->id];

        return route('clientes.documentos.visor', $datoId === null ? $parametros : $parametros + ['dato' => $datoId]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function enlaceEntrevista(array $ctx): ?string
    {
        return Route::has('clientes.ficha.entrevista')
            ? route('clientes.ficha.entrevista', ['cliente' => $ctx['cliente']->id, 'tax_year' => $ctx['anio']])
            : null;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function enlaceRevision(array $ctx): ?string
    {
        return Route::has('clientes.revision.show')
            ? route('clientes.revision.show', ['cliente' => $ctx['cliente']->id, 'tax_year' => $ctx['anio']])
            : null;
    }

    /**
     * Lo sensible solo sale con su máscara: los últimos 4 de un SSN, ITIN o EIN.
     */
    private function mascara(?string $tipo, mixed $valor): string
    {
        $ultimos = is_scalar($valor) ? R01Ssn::ultimos4((string) $valor) : null;

        if ($ultimos === null) {
            return __('Oculto');
        }

        return match ($tipo) {
            'ssn', 'tin' => "***-**-{$ultimos}",
            'ein' => "**-***{$ultimos}",
            default => __('Oculto'),
        };
    }

    private function vacio(mixed $valor): bool
    {
        return $valor === null || $valor === '' || $valor === [];
    }

    private function fecha(CarbonInterface $fecha): string
    {
        return $fecha->locale(app()->getLocale())->isoFormat('D MMM YYYY');
    }

    private function numero(float $valor): string
    {
        return str_starts_with(app()->getLocale(), 'en') ? number_format($valor, 2, '.', ',') : number_format($valor, 2, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function fila(string $id, ?string $destino, string $descripcion, ?string $valor, string $tipo, string $origen, ?string $enlace, string $estado, string $texto, array $extra = []): array
    {
        return [
            'id' => $id,
            'destino' => $destino,
            'descripcion' => $descripcion,
            'valor' => $valor,
            'tipo' => $tipo,
            'sensible' => false,
            'mascara' => null,
            'revelar_url' => null,
            'origen' => $origen,
            'enlace' => $enlace,
            'estado' => $estado,
            'estado_texto' => $texto,
            'confianza' => null,
            ...$extra,
        ];
    }
}
