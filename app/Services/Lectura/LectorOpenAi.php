<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Models\Documento;
use App\Models\Lectura\DocumentoLectura;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\DocumentoAlmacen;
use App\Services\WhatsappAgent\OpenAiClient;
use RuntimeException;

/**
 * Lee un documento con OpenAI (F4.3d): convierte el archivo en imágenes por
 * página (PaginasDocumento), pide las casillas del catálogo con salida
 * estructurada (EsquemaLectura) y calcula la confianza de cada una
 * (CalculadoraConfianza). En un PDF con texto busca además cada valor en esa
 * capa de texto (UbicadorEnPdf): si está, la confianza sube y queda el
 * recuadro para el visor. En fotos y escaneados no hay texto que comparar.
 *
 * Los documentos que no se leen (sin casillas en el catálogo) se mandan al
 * lector manual sin llamar a OpenAI. Los de renglones (`lectura = renglones`,
 * F6 6.3b) tampoco van a OpenAI mientras no exista el lector de renglones con
 * IA (tramo 2): una hoja `xlsx` o `csv` la lee `LectorHojas`, sin IA; un PDF o
 * una foto va al lector manual y la regla R-14 avisa al preparador. Ningún
 * mensaje de error lleva lo leído.
 */
class LectorOpenAi implements LectorDocumentos
{
    public const NOMBRE = 'openai';

    public function __construct(
        private readonly PaginasDocumento $paginas,
        private readonly EsquemaLectura $esquemas,
        private readonly OpenAiClient $openai,
        private readonly CalculadoraConfianza $confianza,
        private readonly DocumentoAlmacen $almacen,
        private readonly LectorManual $manual,
        private readonly LectorHojas $hojas,
        private readonly CatalogoFiscal $catalogo,
    ) {}

    public function leer(Documento $documento, DocumentoLectura $lectura, string $codigo, int $anio): LecturaResultado
    {
        if (($this->catalogo->documentos($anio)->firstWhere('codigo', $codigo)['lectura'] ?? 'casillas') === 'renglones') {
            return in_array(strtolower((string) $documento->formato), ['xlsx', 'csv'], true)
                ? $this->hojas->leer($documento, $lectura, $codigo, $anio)
                : $this->manual->leer($documento, $lectura, $codigo, $anio);
        }

        if (! array_key_exists($codigo, $this->esquemas->documentosLeibles($anio))) {
            return $this->manual->leer($documento, $lectura, $codigo, $anio);
        }

        $modelo = (string) config('services.openai.vision_model');

        throw_if($modelo === '' || (string) config('services.openai.api_key') === '', new RuntimeException('Falta configurar OPENAI_API_KEY y OPENAI_VISION_MODEL para leer documentos.'));

        $cantidad = $this->paginas->generar($documento, $lectura);

        $imagenes = [];

        for ($pagina = 1; $pagina <= $cantidad; $pagina++) {
            $imagenes[] = 'data:image/png;base64,'.base64_encode($this->paginas->leer($lectura, $pagina));
        }

        $leido = $this->openai->leerConEsquema(
            $this->esquemas->instrucciones($codigo, $anio),
            $imagenes,
            $this->esquemas->para($codigo, $anio),
            'lectura_'.preg_replace('/[^A-Za-z0-9]+/', '_', $codigo),
        );

        return new LecturaResultado(
            lector: self::NOMBRE,
            modelo: $modelo,
            tipoDetectado: is_string($leido['tipo_documento'] ?? null) ? $leido['tipo_documento'] : null,
            anioDetectado: is_int($leido['anio'] ?? null) ? $leido['anio'] : null,
            paginas: $cantidad,
            casillas: $this->casillas($documento, $codigo, $anio, (array) ($leido['casillas'] ?? []), ($leido['paginas_legibles'] ?? true) !== false),
        );
    }

    /**
     * @param  array<string|int, mixed>  $leidas
     * @return array<string, array{valor: string|null, legibilidad: Legibilidad, confianza: float, motivo: MotivoConfianza, pagina: int|null, recuadro: array{x: float, y: float, ancho: float, alto: float}|null, evidencia: string|null}>
     */
    private function casillas(Documento $documento, string $codigo, int $anio, array $leidas, bool $paginasLegibles): array
    {
        $ubicador = new UbicadorEnPdf;
        $pdf = $this->pdfEnDisco($documento);
        $resultado = [];

        try {
            foreach ($this->esquemas->casillas($codigo, $anio) as $casilla) {
                $una = (array) ($leidas[$casilla['casilla']] ?? []);
                $valor = isset($una['valor']) && is_scalar($una['valor']) ? trim((string) $una['valor']) : null;
                $valor = $valor === '' ? null : $valor;
                $legibilidad = Legibilidad::tryFrom((string) ($una['legibilidad'] ?? '')) ?? Legibilidad::NoSeVe;

                // Con las hojas borrosas o cortadas, nada se da por claro.
                if (! $paginasLegibles && $legibilidad === Legibilidad::Claro) {
                    $legibilidad = Legibilidad::Dudoso;
                }

                $pagina = isset($una['pagina']) && is_int($una['pagina']) && $una['pagina'] > 0 ? $una['pagina'] : null;
                $evidencia = isset($una['texto_cercano']) && is_string($una['texto_cercano']) && trim($una['texto_cercano']) !== '' ? trim($una['texto_cercano']) : null;

                $ubicado = $pdf !== null && $valor !== null && $legibilidad !== Legibilidad::NoSeVe
                    ? $ubicador->buscar($pdf, $valor, $pagina, $evidencia)
                    : null;

                $evaluacion = $this->confianza->evaluar($casilla, $valor, $legibilidad, $ubicado !== null);

                $resultado[$casilla['casilla']] = [
                    'valor' => $valor,
                    'legibilidad' => $legibilidad,
                    'confianza' => $evaluacion['confianza'],
                    'motivo' => $evaluacion['motivo'],
                    'pagina' => $ubicado['pagina'] ?? $pagina,
                    'recuadro' => $ubicado === null ? null : ['x' => $ubicado['x'], 'y' => $ubicado['y'], 'ancho' => $ubicado['ancho'], 'alto' => $ubicado['alto']],
                    'evidencia' => $evidencia,
                ];
            }
        } finally {
            if ($pdf !== null) {
                @unlink($pdf);
            }
        }

        return $resultado;
    }

    /**
     * Si el archivo es un PDF, una copia temporal descifrada para `pdftotext`
     * (que necesita un archivo); se borra siempre al terminar. Una foto o un
     * HEIC no tienen capa de texto: null.
     */
    private function pdfEnDisco(Documento $documento): ?string
    {
        $contenido = $this->almacen->leer($documento);

        if ($contenido === null || ! str_starts_with($contenido, '%PDF')) {
            return null;
        }

        $ruta = tempnam(sys_get_temp_dir(), 'lectura-');

        throw_if($ruta === false || file_put_contents($ruta, $contenido) === false, new RuntimeException('No se pudo preparar el PDF para ubicar los valores.'));

        return $ruta;
    }
}
