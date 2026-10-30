<?php

namespace App\Services\Entrevista;

use App\Enums\FieldState;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
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
    ) {}

    /**
     * La lista para el cliente, en el idioma que eligió.
     *
     * @return list<array<string, mixed>>
     */
    public function lista(Entrevista $entrevista, SituacionEntrevista $situacion, bool $ingles): array
    {
        $catalogo = $this->catalogo->documentos($entrevista->tax_year)->keyBy('codigo');
        $preguntas = $this->catalogo->preguntas($entrevista->tax_year)->keyBy('codigo');
        $personas = $entrevista->personas()->pluck('orden', 'id');
        $grupos = array_flip(self::GRUPOS);

        $archivos = Documento::query()
            ->where('entrevista_id', $entrevista->id)
            ->whereNotNull('documento_codigo')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Documento $d) => $d->documento_codigo.'#'.$d->persona_id);

        $texto = fn (?string $es, ?string $en) => $es === null ? null : str_replace('{anio}', (string) $entrevista->tax_year, $ingles && $en ? $en : $es);

        return collect($situacion->documentos)
            ->filter(fn (array $d) => $catalogo->has($d['codigo']))
            ->map(function (array $d) use ($catalogo, $preguntas, $personas, $archivos, $texto) {
                $documento = $catalogo->get($d['codigo']);

                return [
                    'codigo' => $d['codigo'],
                    'persona_id' => $d['persona_id'],
                    'persona_orden' => $d['persona_id'] === null ? null : $personas->get($d['persona_id']),
                    'grupo' => $documento['grupo'],
                    'nombre' => $texto($documento['nombre_sencillo_es'], $documento['nombre_sencillo_en']),
                    'siempre' => (bool) $documento['siempre'],
                    'por_que' => collect($d['activado_por'])
                        ->map(fn (string $codigo) => $preguntas->has($codigo) ? $texto($preguntas[$codigo]['texto_es'], $preguntas[$codigo]['texto_en']) : null)
                        ->filter()
                        ->values()
                        ->all(),
                    'formatos' => $documento['formatos'] ?? [],
                    'archivos' => ($archivos->get($d['codigo'].'#'.$d['persona_id']) ?? collect())
                        ->map(fn (Documento $a) => [
                            'id' => $a->id,
                            'nombre' => $a->file_original_name,
                            'tamano' => $a->file_size,
                            'subido_en' => $a->created_at?->toIso8601String(),
                            'url' => $a->previewUrl(),
                        ])->values()->all(),
                    '_orden' => [
                        $documento['siempre'] ? 0 : 1,
                        $grupos[$documento['grupo']] ?? 99,
                        $documento['orden'],
                        $d['persona_id'] === null ? 0 : $personas->get($d['persona_id'], 99),
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
    public function subir(Entrevista $entrevista, string $codigo, ?EntrevistaPersona $persona, UploadedFile $archivo): Documento
    {
        $extension = strtolower((string) $archivo->getClientOriginalExtension());
        $ruta = $archivo->getRealPath();
        $hash = $ruta !== false ? hash_file('sha256', $ruta) : false;

        return Documento::query()->create([
            'user_id' => $entrevista->user_id,
            'tax_year' => $entrevista->tax_year,
            'documento_codigo' => $codigo,
            'entrevista_id' => $entrevista->id,
            'persona_id' => $persona?->id,
            'file_path' => $this->almacen->guardar($archivo, "documentos/{$entrevista->user_id}", Str::uuid().'.'.$extension),
            'file_original_name' => $archivo->getClientOriginalName(),
            'file_mime_type' => $archivo->getMimeType() ?? 'application/octet-stream',
            'file_size' => (int) $archivo->getSize(),
            'formato' => $extension,
            'hash_contenido' => $hash === false ? null : $hash,
            'cifrado' => true,
            'estado_validacion' => FieldState::Recibido,
        ]);
    }

    /**
     * Los formatos que acepta un documento del catálogo, como extensiones.
     *
     * @return list<string>
     */
    public function extensiones(int $taxYear, string $codigo): array
    {
        $formatos = $this->catalogo->documentos($taxYear)->firstWhere('codigo', $codigo)['formatos'] ?? ['pdf', 'jpg', 'png', 'heic'];

        return array_values(array_unique([...$formatos, ...(in_array('jpg', $formatos, true) ? ['jpeg'] : [])]));
    }
}
