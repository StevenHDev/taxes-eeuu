<?php

namespace App\Services\Lectura;

use App\Models\Documento;
use App\Models\Lectura\DocumentoLectura;
use RuntimeException;

/**
 * Quien lee un archivo y devuelve sus datos (F4.3d). Hay dos: `LectorOpenAi`
 * (lee las casillas con visión) y `LectorManual` (no lee nada; el preparador
 * escribe los datos en el visor). La bandera `tax.lectura_automatica` decide
 * cuál se usa.
 */
interface LectorDocumentos
{
    /**
     * @param  string  $codigo  el código del documento que se espera (`w2`, `1099-nec`…)
     * @param  int  $anio  el año fiscal de la entrevista
     *
     * @throws RuntimeException si no se pudo leer; el mensaje nunca lleva datos del cliente
     */
    public function leer(Documento $documento, DocumentoLectura $lectura, string $codigo, int $anio): LecturaResultado;
}
