<?php

namespace App\Services;

use App\Models\Documento;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Único punto para escribir, leer y borrar los archivos de los documentos de
 * clientes (F0.2 del documento madre, mínimo T2). Los archivos se guardan
 * cifrados a nivel de aplicación con `Crypt` (AES-256 con la APP_KEY), sobre
 * el disco que diga `Documento::disco()`.
 *
 * Los documentos subidos antes de F0.2 quedan con `cifrado = false` y se
 * leen tal cual hasta que `documentos:cifrar-existentes` los cifre.
 *
 * ⚠️ Sin la APP_KEY (o una de APP_PREVIOUS_KEYS) los archivos no se pueden
 * leer: tiene que estar respaldada fuera del servidor (F0.4).
 *
 * Límite conocido: el cifrado carga el archivo completo en memoria. Hoy el
 * máximo por archivo es 20 MB, así que es aceptable.
 */
class DocumentoAlmacen
{
    /**
     * Cifra y guarda el archivo subido. Devuelve la ruta relativa al disco.
     */
    public function guardar(UploadedFile $archivo, string $directorio, string $nombre): string
    {
        $ruta = "{$directorio}/{$nombre}";
        $contenido = $archivo->get();

        throw_unless(
            is_string($contenido) && $this->disco()->put($ruta, Crypt::encryptString($contenido)),
            new \RuntimeException('Unable to store the uploaded file.'),
        );

        return $ruta;
    }

    public function existe(Documento $documento): bool
    {
        return $this->disco()->exists($documento->file_path);
    }

    /**
     * Contenido original del documento, o null si el archivo ya no está.
     */
    public function leer(Documento $documento): ?string
    {
        $contenido = $this->disco()->get($documento->file_path);

        if (! is_string($contenido)) {
            return null;
        }

        return $documento->cifrado ? Crypt::decryptString($contenido) : $contenido;
    }

    /**
     * Respuesta HTTP con el documento descifrado, para verlo (`inline`) o
     * descargarlo (`attachment`).
     */
    public function responder(Documento $documento, string $disposicion): Response
    {
        $contenido = $this->leer($documento);

        abort_if($contenido === null, 404, 'El archivo ya no está disponible.');

        $nombre = $documento->file_original_name;
        // El nombre "de respaldo" del header tiene que ser ASCII sin / ni %.
        $nombreAscii = str_replace(['/', '\\', '%'], '-', Str::ascii($nombre));

        return response($contenido, 200, [
            'Content-Type' => $documento->file_mime_type,
            'Content-Length' => (string) strlen($contenido),
            'Content-Disposition' => HeaderUtils::makeDisposition($disposicion, $nombre, $nombreAscii),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function borrar(string $ruta): void
    {
        $this->disco()->delete($ruta);
    }

    /**
     * Cifra en su lugar un archivo guardado antes de F0.2. Devuelve false si
     * el archivo no está en el disco.
     *
     * Se puede correr dos veces sobre el mismo archivo: si el contenido ya es
     * un cifrado válido (por ejemplo, un corte entre escribir el archivo y
     * marcar la fila), no lo vuelve a cifrar. Se escribe primero a un
     * temporal y luego se mueve, para no dejar un archivo a medio escribir.
     */
    public function cifrarExistente(Documento $documento): bool
    {
        $disco = $this->disco();
        $ruta = $documento->file_path;
        $contenido = $disco->get($ruta);

        if (! is_string($contenido)) {
            return false;
        }

        if (! $this->estaCifrado($contenido)) {
            $temporal = "{$ruta}.cifrando";
            $disco->put($temporal, Crypt::encryptString($contenido));
            $disco->move($temporal, $ruta);
        }

        // Sin pasar por Eloquent: no es un cambio que deba quedar en la
        // bitácora (sería una fila por documento).
        Documento::withTrashed()->whereKey($documento->getKey())->update(['cifrado' => true]);

        return true;
    }

    private function estaCifrado(string $contenido): bool
    {
        try {
            Crypt::decryptString($contenido);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    private function disco(): Filesystem
    {
        return Storage::disk(Documento::disco());
    }
}
