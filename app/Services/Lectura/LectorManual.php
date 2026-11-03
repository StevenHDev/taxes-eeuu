<?php

namespace App\Services\Lectura;

use App\Models\Documento;
use App\Models\Lectura\DocumentoLectura;

/**
 * El lector de respaldo (F4.3d): no lee nada ni llama a ningún servicio. Se
 * usa con la lectura automática apagada y con los documentos que no se leen
 * (extractos, recibos…); el preparador escribe los datos en el visor.
 */
class LectorManual implements LectorDocumentos
{
    public const NOMBRE = 'manual';

    public function leer(Documento $documento, DocumentoLectura $lectura, string $codigo, int $anio): LecturaResultado
    {
        return new LecturaResultado(lector: self::NOMBRE);
    }
}
