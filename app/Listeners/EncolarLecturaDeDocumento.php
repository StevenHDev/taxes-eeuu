<?php

namespace App\Listeners;

use App\Events\ArchivoDocumentoSubido;
use App\Jobs\LeerDocumentoJob;

/**
 * Un archivo subido se lee en la cola (F4.4), en el worker: el contenedor web
 * nunca llama a OpenAI. Laravel lo registra solo por el tipo del evento en
 * `handle()` (descubrimiento de listeners): no se enlaza a mano.
 */
class EncolarLecturaDeDocumento
{
    public function handle(ArchivoDocumentoSubido $evento): void
    {
        LeerDocumentoJob::dispatch($evento->documento->id);
    }
}
