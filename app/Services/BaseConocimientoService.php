<?php

namespace App\Services;

use App\Enums\EstadoBaseConocimiento;
use App\Models\BaseConocimientoDocumento;
use App\Models\User;
use App\Services\DocumentoExtraccion\PdfTextExtractorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carga y consulta de la base de conocimiento del agente (ver
 * docs/implementar_agente_n8n.md, "Base de conocimiento"). La conversión a
 * Markdown es deliberadamente simple (encabezado + texto plano ya extraído
 * por PdfTextExtractorService, Nivel 1) — el objetivo es que la tool de
 * consulta no tenga que mandarle PDF crudo al modelo, no preservar el
 * layout original. Solo Nivel 1: un PDF que resulta ser un escaneo sin capa
 * de texto queda en estado Error, no cae a Nivel 2 (visión) — es carga
 * manual de un administrador, puede simplemente resubir un PDF con texto.
 */
class BaseConocimientoService
{
    /**
     * Tope de fragmentos que devuelve buscar() — igual que cualquier
     * resultado de tool, un puñado de fragmentos relevantes es más útil (y
     * más barato en tokens) que todo el documento.
     */
    private const LIMITE_FRAGMENTOS = 5;

    public function __construct(
        private readonly PdfTextExtractorService $extractor,
    ) {}

    public function subir(UploadedFile $file, User $actor): BaseConocimientoDocumento
    {
        $path = $file->storeAs('base_conocimiento', Str::uuid().'.pdf', 'local');

        throw_if($path === false, new \RuntimeException('No se pudo guardar el PDF.'));

        $texto = $this->extractor->extraer(Storage::disk('local')->path($path));

        return BaseConocimientoDocumento::query()->create([
            'nombre_original' => $file->getClientOriginalName(),
            'ruta_pdf' => $path,
            'contenido_markdown' => $texto !== null ? $this->aMarkdown($file->getClientOriginalName(), $texto) : null,
            'tamano' => $file->getSize() ?: 0,
            'estado' => $texto !== null ? EstadoBaseConocimiento::Procesado : EstadoBaseConocimiento::Error,
            'error_mensaje' => $texto !== null ? null : 'No se encontró texto legible en el PDF (¿es un escaneo?).',
            'subido_por_user_id' => $actor->id,
        ]);
    }

    public function eliminar(BaseConocimientoDocumento $documento): void
    {
        Storage::disk('local')->delete($documento->ruta_pdf);
        $documento->delete();
    }

    /**
     * Búsqueda de texto simple (no semántica) sobre los documentos ya
     * procesados: parte cada uno en párrafos y puntúa por cantidad de
     * apariciones de los términos de la consulta, sin ningún ranking más
     * sofisticado — ver la nota en el plan sobre por qué se descartó
     * embeddings/pgvector por ahora.
     *
     * @return array<int, array{documento: string, fragmento: string}>
     */
    public function buscar(string $consulta, int $limite = self::LIMITE_FRAGMENTOS): array
    {
        $terminos = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($consulta))) ?: []));

        if ($terminos === []) {
            return [];
        }

        $candidatos = [];

        BaseConocimientoDocumento::query()
            ->where('estado', EstadoBaseConocimiento::Procesado)
            ->whereNotNull('contenido_markdown')
            ->get(['nombre_original', 'contenido_markdown'])
            ->each(function (BaseConocimientoDocumento $doc) use ($terminos, &$candidatos) {
                foreach (preg_split('/\n{2,}/', (string) $doc->contenido_markdown) ?: [] as $parrafo) {
                    $parrafo = trim($parrafo);
                    $puntaje = $this->puntuar($parrafo, $terminos);

                    if ($puntaje > 0) {
                        $candidatos[] = ['documento' => $doc->nombre_original, 'fragmento' => $parrafo, 'puntaje' => $puntaje];
                    }
                }
            });

        usort($candidatos, fn (array $a, array $b) => $b['puntaje'] <=> $a['puntaje']);

        return collect($candidatos)
            ->take($limite)
            ->map(fn (array $c) => ['documento' => $c['documento'], 'fragmento' => $c['fragmento']])
            ->all();
    }

    /**
     * @param  array<int, string>  $terminos
     */
    private function puntuar(string $parrafo, array $terminos): int
    {
        $parrafoMinuscula = mb_strtolower($parrafo);

        return array_sum(array_map(
            fn (string $termino) => substr_count($parrafoMinuscula, $termino),
            $terminos,
        ));
    }

    private function aMarkdown(string $nombreOriginal, string $texto): string
    {
        return "# {$nombreOriginal}\n\n{$texto}";
    }
}
