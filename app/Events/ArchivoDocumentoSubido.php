<?php

namespace App\Events;

use App\Models\Documento;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un archivo quedó guardado en un ítem del checklist (F4.4). Quien escucha
 * (EncolarLecturaDeDocumento) lo manda a leer en la cola; no lleva nada del
 * contenido, solo el archivo.
 */
class ArchivoDocumentoSubido
{
    use Dispatchable;

    public function __construct(public readonly Documento $documento) {}
}
