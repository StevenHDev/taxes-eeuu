<?php

namespace App\Services\DocumentoExtraccion;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Nivel 1 de extracción (ver docs/implementar_agente_n8n.md, sección
 * "Extracción de documentos"): intenta leer la capa de texto real de un PDF
 * con `smalot/pdfparser` (PHP puro, sin binario externo — ver decisión de
 * arquitectura) y aplica una heurística simple para decidir si el resultado
 * es utilizable. Un PDF que en realidad es una foto/escaneo no tiene capa de
 * texto: el parser no truena, simplemente devuelve una cadena vacía o basura
 * (espacios, artefactos de fuentes embebidas) — por eso la sola ausencia de
 * excepción no basta para confiar en el resultado.
 */
class PdfTextExtractorService
{
    /**
     * Un documento fiscal real (W-2, 1099, 1095-A) siempre trae bastante más
     * que esto en texto legible — un valor bajo es la señal más clara de "en
     * realidad es una imagen".
     */
    private const MIN_CARACTERES_UTILES = 30;

    /**
     * Proporción mínima de caracteres alfanuméricos sobre el total — descarta
     * el caso de "texto" que en realidad es solo espacios/artefactos de
     * fuentes sin glifos Unicode mapeados correctamente.
     */
    private const PROPORCION_MINIMA_ALFANUMERICA = 0.3;

    /**
     * @return string|null el texto extraído si pasa la heurística de calidad; null si el
     *                     PDF no tiene texto útil (candidato a Nivel 2 — visión) o no se pudo leer
     */
    public function extraer(string $rutaArchivo): ?string
    {
        try {
            $texto = trim((new Parser)->parseFile($rutaArchivo)->getText());
        } catch (Throwable) {
            // PDF corrupto, cifrado, o con una estructura que el parser no
            // soporta — no es un error de la app, cae al Nivel 2 igual que un
            // PDF sin texto útil.
            return null;
        }

        return $this->esUtil($texto) ? $texto : null;
    }

    private function esUtil(string $texto): bool
    {
        if (mb_strlen($texto) < self::MIN_CARACTERES_UTILES) {
            return false;
        }

        $alfanumericos = preg_match_all('/[\p{L}\p{N}]/u', $texto);

        return ($alfanumericos / mb_strlen($texto)) >= self::PROPORCION_MINIMA_ALFANUMERICA;
    }
}
