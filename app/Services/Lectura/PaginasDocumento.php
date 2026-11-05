<?php

namespace App\Services\Lectura;

use App\Models\Documento;
use App\Models\Lectura\DocumentoLectura;
use App\Services\DocumentoAlmacen;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Convierte un archivo (PDF, JPG, PNG o HEIC) en una imagen PNG por página,
 * guardada cifrada, para mandarla a OpenAI y mostrarla en el visor (F4.3a).
 *
 * Las páginas se guardan en el mismo disco que los archivos
 * (`Documento::disco()`), en `lecturas/{lectura}/pagina-{n}.png`, cifradas
 * con la APP_KEY como `DocumentoAlmacen`. Nunca queda un PNG en claro: los
 * temporales de la conversión viven en un directorio propio que se borra
 * siempre, haya o no error.
 *
 * Requisitos de infraestructura (Dockerfile): `pdftoppm` (poppler-utils)
 * para PDF, `heif-convert` (libheif-examples) para HEIC y GD con soporte de
 * JPEG. Si falta alguno se lanza una excepción clara, no se falla en
 * silencio.
 */
class PaginasDocumento
{
    /** Un formulario del IRS tiene 1 o 2 páginas; más de esto no es un formulario. */
    public const MAXIMO_PAGINAS = 10;

    /** Lado más largo de la imagen que se guarda: baja el costo y el tamaño sin perder lectura. */
    private const LADO_MAXIMO = 2400;

    private const RESOLUCION_PDF = 150;

    public function __construct(private readonly DocumentoAlmacen $almacen) {}

    /**
     * Genera y guarda las páginas del archivo. Devuelve cuántas son y las
     * deja anotadas en la lectura.
     *
     * @throws RuntimeException si el archivo no se puede leer, no es de un tipo
     *                          soportado, tiene demasiadas páginas o falta una herramienta
     */
    public function generar(Documento $documento, DocumentoLectura $lectura): int
    {
        $contenido = $this->almacen->leer($documento);

        if ($contenido === null || $contenido === '') {
            throw new RuntimeException('El archivo del documento no está en el disco.');
        }

        $directorio = $this->directorioTemporal();

        try {
            $imagenes = match ($this->tipo($contenido, $documento)) {
                'pdf' => $this->paginasDePdf($contenido, $directorio),
                'heic' => [$this->aPng($this->heicAJpg($contenido, $directorio))],
                'jpg', 'png' => [$this->aPng($contenido)],
                default => throw new RuntimeException('Tipo de archivo no soportado para la lectura.'),
            };
        } finally {
            $this->borrarDirectorio($directorio);
        }

        $this->borrarPaginas($lectura);

        foreach ($imagenes as $i => $png) {
            $guardada = Storage::disk(Documento::disco())->put($this->ruta($lectura, $i + 1), Crypt::encryptString($png));

            throw_unless($guardada, new RuntimeException('No se pudo guardar una página del documento.'));
        }

        $lectura->update(['paginas' => count($imagenes)]);

        return count($imagenes);
    }

    /**
     * El PNG de una página (la primera es la 1), descifrado.
     *
     * @throws RuntimeException si la página no existe
     */
    public function leer(DocumentoLectura $lectura, int $pagina): string
    {
        $contenido = Storage::disk(Documento::disco())->get($this->ruta($lectura, $pagina));

        if (! is_string($contenido)) {
            throw new RuntimeException('La página del documento no existe.');
        }

        return Crypt::decryptString($contenido);
    }

    private function ruta(DocumentoLectura $lectura, int $pagina): string
    {
        return "lecturas/{$lectura->id}/pagina-{$pagina}.png";
    }

    /**
     * Una lectura que se rehace no deja páginas de la anterior.
     */
    private function borrarPaginas(DocumentoLectura $lectura): void
    {
        Storage::disk(Documento::disco())->deleteDirectory("lecturas/{$lectura->id}");
    }

    /**
     * El tipo por lo que trae el archivo, no por su extensión; la extensión
     * solo desempata el HEIC, que algunas versiones de libmagic no reconocen.
     */
    private function tipo(string $contenido, Documento $documento): ?string
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);

        return match (true) {
            $mime === 'application/pdf' => 'pdf',
            $mime === 'image/jpeg' => 'jpg',
            $mime === 'image/png' => 'png',
            in_array($mime, ['image/heic', 'image/heif'], true) => 'heic',
            in_array(strtolower((string) $documento->formato), ['heic', 'heif'], true) && $this->pareceHeic($contenido) => 'heic',
            default => null,
        };
    }

    /**
     * Un HEIC es un contenedor ISO: trae `ftyp` en los bytes 4 a 7.
     */
    private function pareceHeic(string $contenido): bool
    {
        return substr($contenido, 4, 4) === 'ftyp';
    }

    /**
     * @return list<string> un PNG por página
     */
    private function paginasDePdf(string $contenido, string $directorio): array
    {
        $binario = $this->binario('pdftoppm', 'poppler-utils');

        $entrada = "{$directorio}/entrada.pdf";
        file_put_contents($entrada, $contenido);

        // Pide una página más que el máximo: si sale, el archivo tiene demasiadas.
        $proceso = new Process([$binario, '-png', '-r', (string) self::RESOLUCION_PDF, '-f', '1', '-l', (string) (self::MAXIMO_PAGINAS + 1), $entrada, "{$directorio}/pagina"]);
        $proceso->setTimeout(120);
        $proceso->run();

        // El mensaje de pdftoppm puede traer texto del documento: no se copia.
        throw_if(! $proceso->isSuccessful(), new RuntimeException('No se pudo convertir el PDF en imágenes (pdftoppm falló).'));

        $paginas = glob("{$directorio}/pagina-*.png") ?: [];
        natsort($paginas);

        throw_if($paginas === [], new RuntimeException('El PDF no tiene páginas que se puedan leer.'));
        throw_if(count($paginas) > self::MAXIMO_PAGINAS, new RuntimeException('El PDF tiene demasiadas páginas para ser un formulario ('.self::MAXIMO_PAGINAS.' como máximo).'));

        return array_values(array_map(fn (string $ruta) => $this->aPng((string) file_get_contents($ruta)), $paginas));
    }

    /**
     * HEIC → JPG con `heif-convert`; el resultado sigue el camino de un JPG.
     */
    private function heicAJpg(string $contenido, string $directorio): string
    {
        $binario = $this->binario('heif-convert', 'libheif-examples');

        $entrada = "{$directorio}/entrada.heic";
        $salida = "{$directorio}/salida.jpg";
        file_put_contents($entrada, $contenido);

        $proceso = new Process([$binario, $entrada, $salida]);
        $proceso->setTimeout(120);
        $proceso->run();

        // heif-convert pone un sufijo (`salida-1.jpg`) si el archivo tiene varias imágenes.
        $resultado = is_file($salida) ? $salida : (glob("{$directorio}/salida*.jpg")[0] ?? null);

        throw_if(! $proceso->isSuccessful() || $resultado === null, new RuntimeException('No se pudo convertir la foto HEIC (heif-convert falló).'));

        return (string) file_get_contents($resultado);
    }

    /**
     * Normaliza una imagen a PNG: respeta la orientación de la cámara (EXIF),
     * baja el lado más largo a LADO_MAXIMO y quita los metadatos.
     */
    private function aPng(string $contenido): string
    {
        throw_unless(function_exists('imagecreatefromstring'), new RuntimeException('GD no está instalado en este host.'));

        if (str_starts_with($contenido, "\xFF\xD8")) {
            throw_unless(function_exists('imagecreatefromjpeg'), new RuntimeException('GD no tiene soporte de JPEG en este host (hace falta libjpeg y --with-jpeg).'));
        }

        try {
            $imagen = @imagecreatefromstring($contenido);
        } catch (Throwable) {
            $imagen = false;
        }

        throw_if($imagen === false, new RuntimeException('La imagen no se pudo leer (está dañada o es de un formato no soportado).'));

        $imagen = $this->enderezar($imagen, $contenido);
        $imagen = $this->reducir($imagen);

        ob_start();
        imagepng($imagen);
        $png = (string) ob_get_clean();

        return $png;
    }

    /**
     * Las fotos de celular guardan la orientación en el EXIF, no en los
     * píxeles: sin esto, un documento vertical llegaría acostado al lector.
     */
    private function enderezar(\GdImage $imagen, string $contenido): \GdImage
    {
        if (! str_starts_with($contenido, "\xFF\xD8") || ! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contenido));
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($grados === 0) {
            return $imagen;
        }

        $girada = imagerotate($imagen, $grados, 0);

        return $girada === false ? $imagen : $girada;
    }

    private function reducir(\GdImage $imagen): \GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $mayor = max($ancho, $alto);

        if ($mayor <= self::LADO_MAXIMO) {
            return $imagen;
        }

        $reducida = imagescale($imagen, (int) round($ancho * self::LADO_MAXIMO / $mayor), (int) round($alto * self::LADO_MAXIMO / $mayor));

        return $reducida === false ? $imagen : $reducida;
    }

    private function binario(string $nombre, string $paquete): string
    {
        $binario = (new ExecutableFinder)->find($nombre);

        throw_if($binario === null, new RuntimeException("{$nombre} ({$paquete}) no está instalado en este host: es requisito de infraestructura para la lectura de documentos."));

        return $binario;
    }

    private function directorioTemporal(): string
    {
        $base = (string) (config('tax.lectura_temporal') ?: sys_get_temp_dir());
        $directorio = $base.'/lectura_'.bin2hex(random_bytes(8));

        throw_unless(mkdir($directorio, 0700), new RuntimeException('No se pudo crear el directorio temporal.'));

        return $directorio;
    }

    private function borrarDirectorio(string $directorio): void
    {
        foreach (glob("{$directorio}/*") ?: [] as $archivo) {
            @unlink($archivo);
        }

        @rmdir($directorio);
    }
}
