<?php

namespace App\Services\DocumentoExtraccion;

use App\Services\WhatsappAgent\OpenAiClient;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Nivel 2 de extracción (respaldo) — ver docs/implementar_agente_n8n.md,
 * sección "Extracción de documentos": transcripción vía modelo de visión
 * cuando el Nivel 1 (PdfTextExtractorService) no encontró texto útil, o el
 * archivo entrante ya es una imagen.
 *
 * Prerrequisito de infraestructura: rasterizar un PDF sin texto útil requiere
 * `pdftoppm` (paquete `poppler-utils`) instalado en el host que corre la
 * cola — a diferencia del Nivel 1 (deliberadamente sin binario externo, ver
 * decisión de arquitectura sobre smalot/pdfparser), no hay alternativa 100%
 * PHP para rasterizar páginas de PDF sin depender de un binario del sistema
 * o de la extensión Imagick (tampoco garantizable en todo host). Se lanza
 * una excepción clara si el binario no está disponible, en vez de fallar en
 * silencio.
 */
class DocumentoVisionExtractorService
{
    private const PROMPT_TRANSCRIPCION = 'Transcribe todo el texto visible en esta imagen tal cual aparece, sin completar, '
        .'inferir ni asumir ningún valor que no se lea con certeza. Donde no puedas leer algo, escribe exactamente '
        .'"[no legible]" en ese punto. No agregues explicaciones, resúmenes ni comentarios propios — solo la transcripción literal.';

    public function __construct(private readonly OpenAiClient $openAi) {}

    /**
     * @param  string  $mimeType  ej. "application/pdf", "image/jpeg" — determina si hace
     *                            falta rasterizar antes de transcribir.
     */
    public function transcribir(string $rutaArchivo, string $mimeType): string
    {
        $imagenes = $mimeType === 'application/pdf'
            ? $this->rasterizarPdf($rutaArchivo)
            : [$rutaArchivo];

        return collect($imagenes)
            ->map(fn (string $ruta) => $this->openAi->transcribirImagen(self::PROMPT_TRANSCRIPCION, $this->comoDataUrl($ruta)))
            ->implode("\n\n");
    }

    /**
     * @return array<int, string> rutas locales de una imagen PNG por página, en orden
     */
    private function rasterizarPdf(string $rutaPdf): array
    {
        $binario = (new ExecutableFinder)->find('pdftoppm');

        throw_if($binario === null, new RuntimeException(
            'pdftoppm (poppler-utils) no está instalado en este host — es requisito de infraestructura '
            .'para rasterizar un PDF sin texto útil antes de mandarlo a visión (ver DocumentoVisionExtractorService).',
        ));

        // pdftoppm arma sus propios nombres de archivo a partir de este
        // prefijo (`{prefijo}-1.png`, `{prefijo}-2.png`, ...) — el prefijo en
        // sí no debe existir como archivo.
        $prefijo = tempnam(sys_get_temp_dir(), 'pdf_pagina_');
        unlink($prefijo);

        $proceso = new Process([$binario, '-png', '-r', '150', $rutaPdf, $prefijo]);
        $proceso->run();

        throw_if(! $proceso->isSuccessful(), new RuntimeException(
            'pdftoppm falló al rasterizar el PDF: '.$proceso->getErrorOutput(),
        ));

        $paginas = glob("{$prefijo}*.png") ?: [];
        sort($paginas);

        return $paginas;
    }

    private function comoDataUrl(string $rutaImagen): string
    {
        $mime = mime_content_type($rutaImagen) ?: 'image/png';
        $contenido = (string) file_get_contents($rutaImagen);

        return "data:{$mime};base64,".base64_encode($contenido);
    }
}
