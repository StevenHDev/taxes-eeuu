<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Enums\Lectura\EstadoLectura;
use App\Http\Requests\CorregirDatoRequest;
use App\Models\BitacoraActividad;
use App\Models\Documento;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Lectura\CalculadoraConfianza;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\EsquemaLectura;
use App\Services\Lectura\ExplicadorExcepcion;
use App\Services\Lectura\PaginasDocumento;
use App\Support\ParametrosFiscales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use RuntimeException;

/**
 * El visor de un documento leído (F4.6): las imágenes de sus páginas al lado
 * de los datos que se leyeron, para que el preparador los revise y corrija.
 * Lo sensible (SSN, EIN...) nunca viaja en claro en la página: se revela
 * dato por dato con contraseña reciente y queda registrado en la bitácora,
 * como en F2.9. Las imágenes se descifran al vuelo y no se cachean.
 */
class VisorDocumentoController extends Controller
{
    public function show(User $cliente, EntrevistaDocumento $item, DatosDocumento $datos, EsquemaLectura $esquema, CatalogoFiscal $catalogo): InertiaResponse
    {
        $this->authorize('view', $cliente);
        $this->asegurarDelCliente($cliente, $item);

        $anio = $item->entrevista->tax_year;
        $casillas = $this->casillas($esquema, $item->documento_codigo, $anio);
        $vigentes = $datos->vigentes($item);
        $excepciones = Excepcion::query()->abiertas()
            ->whereIn('documento_dato_id', $vigentes->modelKeys())
            ->get()
            ->groupBy('documento_dato_id');
        $explicador = app(ExplicadorExcepcion::class);
        $reglas = $catalogo->reglas($anio)->keyBy('codigo');
        // Las excepciones de todo el documento, no solo las de un dato: R-06 (año o tipo) y R-07 (repetido) no son de una casilla.
        $delDocumento = Excepcion::query()->abiertas()->where('entrevista_documento_id', $item->id)->orderBy('id')->get();
        $correctores = User::withTrashed()->whereIn('id', $vigentes->pluck('corregido_por')->filter()->unique())->pluck('name', 'id');
        $lecturas = $this->lecturasVigentes($item);

        $documento = $catalogo->documentos($anio)->firstWhere('codigo', $item->documento_codigo);

        return Inertia::render('clientes/visor', [
            'cliente' => ['id' => $cliente->id, 'name' => $cliente->name],
            'item' => [
                'id' => $item->id,
                'codigo' => $item->documento_codigo,
                'nombre' => $item->esOtro() ? $item->descripcion : ($documento['nombre_sencillo_es'] ?? $item->documento_codigo),
                'nombre_oficial' => $documento['nombre_oficial'] ?? null,
                'numero' => $item->numero,
                'estado' => $item->estado->value,
                'observacion' => $item->observacion,
                'tax_year' => $anio,
            ],
            'archivos' => $item->archivos->map(fn (Documento $a) => [
                'id' => $a->id,
                'nombre' => $a->file_original_name,
                'formato' => $a->formato,
                'url' => $a->previewUrl(),
            ])->values(),
            'lecturas' => $lecturas->map(fn (DocumentoLectura $l) => [
                'id' => $l->id,
                'documento_id' => $l->documento_id,
                'paginas' => $l->paginas,
                'tipo_detectado' => $l->tipo_detectado,
                'anio_detectado' => $l->anio_detectado,
                'terminada_en' => $l->terminada_en?->toIso8601String(),
                'imagenes' => collect(range(1, max($l->paginas, 0)))
                    ->filter(fn (int $n) => $n >= 1)
                    ->map(fn (int $n) => route('clientes.documentos.visor.pagina', [$cliente, $item, $l, $n]))
                    ->values(),
            ])->values(),
            'excepciones' => $delDocumento->map(function (Excepcion $e) use ($explicador, $reglas) {
                $explicacion = $explicador->explicar($e);

                return [
                    'id' => $e->id,
                    'regla_codigo' => $e->regla_codigo,
                    'regla_descripcion' => $reglas->get($e->regla_codigo)['descripcion'] ?? null,
                    'severidad' => $e->severidad->value,
                    'estado' => $e->estado->value,
                    'casilla' => $e->detalle['casilla'] ?? null,
                    'caso' => $explicacion['caso'],
                    'que_hacer' => $explicacion['que_hacer'],
                ];
            })->values(),
            'datos' => $vigentes->map(fn (DocumentoDato $d) => $this->dato($d, $casillas[$d->casilla] ?? null, $excepciones->get($d->id, collect()), $correctores))->values(),
            'confianza_tabla' => CalculadoraConfianza::tabla(),
            'umbral' => ParametrosFiscales::valor($anio, 'lectura', 'umbral_confianza'),
        ]);
    }

    /**
     * La imagen PNG de una página, descifrada al vuelo. Nunca se guarda en
     * claro ni se cachea fuera del navegador de quien la pidió.
     */
    public function pagina(User $cliente, EntrevistaDocumento $item, DocumentoLectura $lectura, int $pagina, PaginasDocumento $paginas): Response
    {
        $this->authorize('view', $cliente);
        $this->asegurarDelCliente($cliente, $item);
        abort_unless($lectura->entrevista_documento_id === $item->id, 404);
        abort_if($pagina < 1 || $pagina > $lectura->paginas, 404);

        try {
            $png = $paginas->leer($lectura, $pagina);
        } catch (RuntimeException) {
            abort(404);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reveal(Request $request, User $cliente, EntrevistaDocumento $item, DocumentoDato $dato, DatosDocumento $datos): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->asegurarDelCliente($cliente, $item);
        $this->asegurarVigente($datos, $item, $dato);

        // La bitácora guarda quién reveló qué dato, nunca el valor.
        BitacoraActividad::query()->create([
            'actor_id' => $request->user()->id,
            'actor_nombre' => $request->user()->name,
            'actor_email' => $request->user()->email,
            'accion' => AccionAuditoria::Actualizado,
            'auditable_type' => $dato::class,
            'auditable_id' => $dato->id,
            'etiqueta' => "Reveló el dato {$dato->casilla} de {$item->documento_codigo} (cliente #{$cliente->id})",
            'campos_afectados' => ['revelado'],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['valor' => $dato->valor, 'evidencia' => $dato->evidencia]);
    }

    public function corregir(CorregirDatoRequest $request, User $cliente, EntrevistaDocumento $item, DocumentoDato $dato, DatosDocumento $datos): RedirectResponse
    {
        $this->asegurarDelCliente($cliente, $item);
        $this->asegurarVigente($datos, $item, $dato);

        try {
            $datos->corregir($dato, $request->validated('valor'), $request->validated('motivo'), $request->user());
        } catch (TransicionNoPermitida $e) {
            throw ValidationException::withMessages(['motivo' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dato corregido.')]);

        return back();
    }

    /**
     * Un dato del visor, con lo sensible enmascarado: el valor y la evidencia
     * (que trae el texto impreso al lado) salen nulos y se piden con `revelar`.
     *
     * @param  array{casilla: string, descripcion: string, tipo_dato: string|null, clave: bool, sensible: bool}|null  $casilla
     * @param  Collection<int, Excepcion>  $excepciones
     * @param  Collection<int, string>  $correctores
     * @return array<string, mixed>
     */
    private function dato(DocumentoDato $dato, ?array $casilla, $excepciones, $correctores): array
    {
        // Una casilla que el catálogo ya no conoce se trata como sensible.
        $sensible = $casilla['sensible'] ?? true;

        return [
            'id' => $dato->id,
            'lectura_id' => $dato->lectura_id,
            'casilla' => $dato->casilla,
            'descripcion' => $casilla['descripcion'] ?? $dato->casilla,
            'tipo_dato' => $casilla['tipo_dato'] ?? null,
            'clave' => $casilla['clave'] ?? false,
            'sensible' => $sensible,
            'valor' => $sensible ? null : $dato->valor,
            'tiene_valor' => $dato->valor !== null && $dato->valor !== '',
            'confianza' => $dato->confianza,
            'motivo_confianza' => $dato->motivo_confianza === null ? null : [
                'codigo' => $dato->motivo_confianza->value,
                'titulo' => $dato->motivo_confianza->titulo(),
                'explicacion' => $dato->motivo_confianza->explicacion(),
            ],
            'legibilidad' => $dato->legibilidad?->value,
            'pagina' => $dato->pagina,
            'recuadro' => $dato->recuadro,
            'evidencia' => $sensible ? null : $dato->evidencia,
            'origen' => $dato->origen->value,
            'corregido_por' => $dato->corregido_por === null ? null : $correctores->get($dato->corregido_por),
            'corregido_en' => $dato->corregido_en?->toIso8601String(),
            'motivo' => $dato->motivo,
            'excepciones' => $excepciones->map(fn (Excepcion $e) => [
                'id' => $e->id,
                'regla_codigo' => $e->regla_codigo,
                'severidad' => $e->severidad->value,
                'estado' => $e->estado->value,
                'detalle' => $e->detalle,
                'caso' => app(ExplicadorExcepcion::class)->explicar($e)['caso'],
            ])->values(),
        ];
    }

    /**
     * Las casillas del catálogo por código; un documento "otro" o sin
     * casillas no tiene ninguna.
     *
     * @return array<string, array{casilla: string, descripcion: string, tipo_dato: string|null, clave: bool, sensible: bool}>
     */
    private function casillas(EsquemaLectura $esquema, string $codigo, int $anio): array
    {
        try {
            return collect($esquema->casillas($codigo, $anio))->keyBy('casilla')->all();
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /**
     * La última lectura terminada de cada archivo del ítem (las que cuentan).
     *
     * @return Collection<int, DocumentoLectura>
     */
    private function lecturasVigentes(EntrevistaDocumento $item)
    {
        return DocumentoLectura::query()
            ->where('entrevista_documento_id', $item->id)
            ->where('estado', EstadoLectura::Leida)
            ->whereHas('documento')
            ->orderByDesc('id')
            ->get()
            ->unique('documento_id')
            ->sortBy('documento_id');
    }

    private function asegurarDelCliente(User $cliente, EntrevistaDocumento $item): void
    {
        abort_unless($item->entrevista->user_id === $cliente->id, 404);
    }

    /**
     * El dato tiene que ser de este ítem y de una lectura que cuente.
     */
    private function asegurarVigente(DatosDocumento $datos, EntrevistaDocumento $item, DocumentoDato $dato): void
    {
        abort_unless($dato->entrevista_documento_id === $item->id && $datos->vigentes($item)->contains('id', $dato->id), 404);
    }
}
