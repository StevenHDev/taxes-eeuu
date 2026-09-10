<?php

namespace App\Enums;

/**
 * Qué nivel de DocumentoExtraccionService resolvió el texto de un documento
 * — ver docs/implementar_agente_n8n.md, sección "Extracción de documentos".
 */
enum MetodoExtraccionDocumento: string
{
    case TextoPdf = 'texto_pdf';
    case TextoPdfNormalizado = 'texto_pdf_normalizado';
    case Vision = 'vision';
}
