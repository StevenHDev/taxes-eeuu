<?php

namespace App\Services\Entrevista;

use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\FieldState;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\DocumentoAlmacen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Los documentos que pide la entrevista (F2.6, lista mínima): los que se
 * piden siempre y uno por cada activación, por persona en el bloque C, con
 * los archivos que el cliente ya subió. Se suben solo por el portal y se
 * guardan cifrados (DocumentoAlmacen). No se leen con IA: la lectura por
 * casilla es la fase 4. "No aplica", estados y recordatorios son la fase 3.
 */
class DocumentosEntrevista
{
    /**
     * Mismo orden de grupos que la pestaña de documentos del catálogo (lo de
     * identidad primero, como el checklist del cliente, 5.1).
     */
    private const GRUPOS = ['identidad', 'trabajo', 'negocio', 'jubilacion', 'inversiones', 'otros_ingresos', 'gastos', 'situaciones', 'pagos'];

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly DocumentoAlmacen $almacen,
        private readonly ChecklistDocumentos $checklist,
    ) {}

    /**
     * La lista para el cliente, en el idioma que eligió: una fila por ítem
     * vigente del checklist (F3.3). Primero lo que el cliente debe corregir
     * (observación o "no aplica" rechazado), luego la identidad, luego por
     * grupo del catálogo, persona y número; los "otro" al final.
     *
     * @return list<array<string, mixed>>
     */
    public function lista(Entrevista $entrevista, SituacionEntrevista $situacion, bool $ingles): array
    {
        $items = $this->checklist->vigentes($entrevista);

        if ($items->isEmpty() && $situacion->documentos !== []) {
            $this->checklist->sincronizar($entrevista, $situacion);
            $items = $this->checklist->vigentes($entrevista);
        }

        $catalogo = $this->catalogo->documentos($entrevista->tax_year)->keyBy('codigo');
        $preguntas = $this->catalogo->preguntas($entrevista->tax_year)->keyBy('codigo');
        $personas = $entrevista->personas()->pluck('orden', 'id');
        $grupos = array_flip(self::GRUPOS);
        $activados = collect($situacion->documentos)->keyBy(fn (array $d) => ChecklistDocumentos::clave($d['codigo'], $d['persona_id'], 0));
        $repetidos = $items->countBy(fn (EntrevistaDocumento $i) => ChecklistDocumentos::clave($i->documento_codigo, $i->persona_id, 0));

        $texto = fn (?string $es, ?string $en) => $es === null ? null : str_replace('{anio}', (string) $entrevista->tax_year, $ingles && $en ? $en : $es);

        return $items
            ->filter(fn (EntrevistaDocumento $i) => $i->esOtro() || $catalogo->has($i->documento_codigo))
            ->map(function (EntrevistaDocumento $item) use ($catalogo, $preguntas, $personas, $activados, $repetidos, $grupos, $texto) {
                $otro = $item->esOtro();
                $documento = $otro ? null : $catalogo->get($item->documento_codigo);
                $unoPor = $documento['uno_por'] ?? null;
                $activado = $activados->get(ChecklistDocumentos::clave($item->documento_codigo, $item->persona_id, 0));
                $llegada = $documento['fecha_llegada'] ?? null;

                return [
                    'id' => $item->id,
                    'codigo' => $item->documento_codigo,
                    'numero' => $repetidos->get(ChecklistDocumentos::clave($item->documento_codigo, $item->persona_id, 0), 0) > 1 ? $item->numero : null,
                    'persona_id' => $item->persona_id,
                    'persona_orden' => $item->persona_id === null ? null : $personas->get($item->persona_id),
                    'descripcion' => $item->descripcion,
                    'grupo' => $documento['grupo'] ?? 'otro',
                    'nombre' => $otro ? $item->descripcion : $texto($documento['nombre_sencillo_es'], $documento['nombre_sencillo_en']),
                    'nombre_oficial' => $documento['nombre_oficial'] ?? null,
                    'siempre' => (bool) ($documento['siempre'] ?? false),
                    'por_que' => collect($activado['activado_por'] ?? [])
                        ->map(fn (string $codigo) => $preguntas->has($codigo) ? $texto($preguntas[$codigo]['texto_es'], $preguntas[$codigo]['texto_en']) : null)
                        ->filter()
                        ->values()
                        ->all(),
                    'emisor' => $documento === null ? null : $texto($documento['emisor_es'] ?? null, $documento['emisor_en'] ?? null),
                    'fecha_llegada' => $llegada === null ? null : substr((string) $llegada, 0, 10),
                    'si_no_llega' => $documento === null ? null : $texto($documento['si_no_llega_es'] ?? null, $documento['si_no_llega_en'] ?? null),
                    'formatos' => $otro ? ['pdf', 'jpg', 'png', 'heic'] : ($documento['formatos'] ?? []),
                    'estado' => $item->estado->value,
                    'resuelto' => $item->estado->resueltoParaElCliente(),
                    'motivo_no_aplica' => $item->motivo_no_aplica,
                    'observacion' => $item->observacion,
                    'puede_agregar_otro' => in_array($unoPor, ['fuente', 'empleo', 'poliza'], true),
                    'archivos' => $item->archivos
                        ->map(fn (Documento $a) => [
                            'id' => $a->id,
                            'nombre' => $a->file_original_name,
                            'tamano' => $a->file_size,
                            'subido_en' => $a->created_at?->toIso8601String(),
                            'url' => $a->previewUrl(),
                        ])->values()->all(),
                    '_orden' => [
                        in_array($item->estado, [EstadoDocumento::ConObservacion, EstadoDocumento::NoAplicaRechazado], true) ? 0 : 1,
                        $otro ? 1 : 0,
                        ($documento['siempre'] ?? false) ? 0 : 1,
                        $grupos[$documento['grupo'] ?? ''] ?? 99,
                        $documento['orden'] ?? 0,
                        $item->persona_id === null ? 0 : $personas->get($item->persona_id, 99),
                        $item->numero,
                        $item->id,
                    ],
                ];
            })
            ->sortBy('_orden')
            ->map(fn (array $d) => array_diff_key($d, ['_orden' => true]))
            ->values()
            ->all();
    }

    /**
     * Guarda un archivo de un documento de la lista, cifrado. El hash del
     * archivo original sirve para detectar el mismo documento en otra cuenta
     * (DocumentoDuplicadoService).
     */
    public function subir(EntrevistaDocumento $item, UploadedFile $archivo): Documento
    {
        $entrevista = $item->entrevista;
        $extension = strtolower((string) $archivo->getClientOriginalExtension());
        $ruta = $archivo->getRealPath();
        $hash = $ruta !== false ? hash_file('sha256', $ruta) : false;

        $documento = Documento::query()->create([
            'user_id' => $entrevista->user_id,
            'tax_year' => $entrevista->tax_year,
            'documento_codigo' => $item->documento_codigo,
            'entrevista_id' => $entrevista->id,
            'persona_id' => $item->persona_id,
            'entrevista_documento_id' => $item->id,
            'file_path' => $this->almacen->guardar($archivo, "documentos/{$entrevista->user_id}", Str::uuid().'.'.$extension),
            'file_original_name' => $archivo->getClientOriginalName(),
            'file_mime_type' => $archivo->getMimeType() ?? 'application/octet-stream',
            'file_size' => (int) $archivo->getSize(),
            'formato' => $extension,
            'hash_contenido' => $hash === false ? null : $hash,
            'cifrado' => true,
            'estado_validacion' => FieldState::Recibido,
        ]);

        $this->checklist->archivoSubido($item);

        return $documento;
    }

    /**
     * Los formatos que acepta un documento del catálogo, como extensiones.
     *
     * @return list<string>
     */
    public function extensiones(int $taxYear, string $codigo): array
    {
        if ($codigo === EntrevistaDocumento::OTRO) {
            return ['pdf', 'jpg', 'jpeg', 'png', 'heic'];
        }

        $formatos = $this->catalogo->documentos($taxYear)->firstWhere('codigo', $codigo)['formatos'] ?? ['pdf', 'jpg', 'png', 'heic'];

        return array_values(array_unique([...$formatos, ...(in_array('jpg', $formatos, true) ? ['jpeg'] : [])]));
    }
}
