<?php

namespace App\Services\DocumentoExtraccion;

use App\Enums\MetodoExtraccionDocumento;

/**
 * Orquesta los dos niveles de extracción (ver docs/implementar_agente_n8n.md,
 * sección "Extracción de documentos"): intenta primero el Nivel 1 (texto
 * embebido, gratis) si el archivo es un PDF, y solo cae al Nivel 2 (visión)
 * cuando el Nivel 1 no aplica o no dio texto útil.
 *
 * No implementa el sub-paso opcional de "texto_pdf_normalizado" (pasar el
 * texto del Nivel 1 por un modelo de texto económico cuando salió desordenado)
 * — el plan lo marca explícitamente como opcional; queda para cuando haya
 * evidencia real de que el orden del texto extraído confunde al flujo de
 * recolección. El enum `MetodoExtraccionDocumento::TextoPdfNormalizado` ya
 * existe para cuando se implemente.
 */
class DocumentoExtraccionService
{
    public function __construct(
        private readonly PdfTextExtractorService $textoPdf,
        private readonly DocumentoVisionExtractorService $vision,
    ) {}

    /**
     * @return array{texto: string, metodo: MetodoExtraccionDocumento}
     */
    public function extraer(string $rutaArchivo, string $mimeType): array
    {
        if ($mimeType === 'application/pdf') {
            $texto = $this->textoPdf->extraer($rutaArchivo);

            if ($texto !== null) {
                return ['texto' => $texto, 'metodo' => MetodoExtraccionDocumento::TextoPdf];
            }
        }

        return [
            'texto' => $this->vision->transcribir($rutaArchivo, $mimeType),
            'metodo' => MetodoExtraccionDocumento::Vision,
        ];
    }
}
