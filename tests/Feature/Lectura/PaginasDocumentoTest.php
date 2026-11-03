<?php

namespace Tests\Feature\Lectura;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoLectura;
use App\Models\User;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Lectura\PaginasDocumento;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * F4.3a: cada archivo (PDF, JPG, PNG o HEIC) pasa a una imagen PNG por
 * página, guardada cifrada, para mandarla a OpenAI y mostrarla en el visor.
 */
class PaginasDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n";

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $guardador = app(RespuestaGuardador::class);
        $this->entrevista = $guardador->entrevistaDe($cliente, CanalRespuesta::Portal);
        $guardador->guardar($this->entrevista, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $cliente);
    }

    private function item(): EntrevistaDocumento
    {
        return $this->entrevista->documentos()->where('documento_codigo', 'w2')->firstOrFail();
    }

    private function subir(string $nombre, string $contenido): Documento
    {
        return app(DocumentosEntrevista::class)->subir($this->item(), UploadedFile::fake()->createWithContent($nombre, $contenido));
    }

    private function lectura(Documento $archivo): DocumentoLectura
    {
        return DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $archivo->entrevista_documento_id,
            'lector' => 'openai',
            'estado' => EstadoLectura::EnCola,
        ]);
    }

    private function pdf(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/lectura/dos-paginas.pdf'));
    }

    private function imagen(string $formato, int $ancho = 300, int $alto = 200): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefilledrectangle($imagen, 0, 0, $ancho, $alto, imagecolorallocate($imagen, 240, 240, 240));

        ob_start();
        $formato === 'jpg' ? imagejpeg($imagen) : imagepng($imagen);

        return (string) ob_get_clean();
    }

    /**
     * @return array{int, int} ancho y alto del PNG
     */
    private function medidas(string $png): array
    {
        [$ancho, $alto] = getimagesizefromstring($png);

        return [$ancho, $alto];
    }

    public function test_un_pdf_de_dos_paginas_da_dos_pngs_cifrados(): void
    {
        $archivo = $this->subir('w2.pdf', $this->pdf());
        $lectura = $this->lectura($archivo);

        $paginas = app(PaginasDocumento::class)->generar($archivo, $lectura);

        $this->assertSame(2, $paginas);
        $this->assertSame(2, $lectura->fresh()->paginas);

        foreach ([1, 2] as $pagina) {
            $png = app(PaginasDocumento::class)->leer($lectura, $pagina);
            $this->assertStringStartsWith(self::PNG, $png, "La página {$pagina} no es un PNG.");
            [$ancho, $alto] = $this->medidas($png);
            $this->assertGreaterThan(1000, $alto, 'A 150 dpi una página carta mide unos 1650 px de alto.');
            $this->assertGreaterThan(900, $ancho);
        }
    }

    public function test_en_el_disco_no_queda_ningun_png_en_claro(): void
    {
        $archivo = $this->subir('w2.pdf', $this->pdf());
        $lectura = $this->lectura($archivo);

        app(PaginasDocumento::class)->generar($archivo, $lectura);

        $guardadas = collect(Storage::disk(Documento::disco())->allFiles("lecturas/{$lectura->id}"));
        $this->assertCount(2, $guardadas);

        foreach ($guardadas as $ruta) {
            $this->assertStringNotContainsString('PNG', substr((string) Storage::disk(Documento::disco())->get($ruta), 0, 16), "{$ruta} quedó en claro.");
        }

        $this->assertSame([], glob(sys_get_temp_dir().'/lectura_*') ?: [], 'Quedó un directorio temporal.');
    }

    public function test_un_jpg_y_un_png_dan_una_pagina(): void
    {
        if (! function_exists('imagecreatefromjpeg')) {
            $this->markTestSkipped('GD sin soporte de JPEG.');
        }

        foreach (['jpg' => 'foto.jpg', 'png' => 'foto.png'] as $formato => $nombre) {
            $archivo = $this->subir($nombre, $this->imagen($formato));
            $lectura = $this->lectura($archivo);

            $this->assertSame(1, app(PaginasDocumento::class)->generar($archivo, $lectura), $formato);
            $this->assertSame([300, 200], $this->medidas(app(PaginasDocumento::class)->leer($lectura, 1)));
        }
    }

    public function test_una_foto_muy_grande_se_reduce_sin_deformarse(): void
    {
        $archivo = $this->subir('grande.png', $this->imagen('png', 4000, 3000));
        $lectura = $this->lectura($archivo);

        app(PaginasDocumento::class)->generar($archivo, $lectura);

        $this->assertSame([2400, 1800], $this->medidas(app(PaginasDocumento::class)->leer($lectura, 1)));
    }

    public function test_una_foto_de_celular_girada_por_el_exif_se_endereza(): void
    {
        if (! function_exists('imagecreatefromjpeg') || ! function_exists('exif_read_data')) {
            $this->markTestSkipped('GD sin JPEG o sin exif.');
        }

        // Un JPG horizontal (300 × 200) con orientación EXIF 6 ("girar 90°"): sale vertical.
        $jpg = $this->imagen('jpg');
        $exif = "Exif\0\0MM\0*\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0\x06\0\0\0\0\0\0";
        $segmento = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
        $conExif = substr($jpg, 0, 2).$segmento.substr($jpg, 2);

        $archivo = $this->subir('celular.jpg', $conExif);
        $lectura = $this->lectura($archivo);

        app(PaginasDocumento::class)->generar($archivo, $lectura);

        $this->assertSame([200, 300], $this->medidas(app(PaginasDocumento::class)->leer($lectura, 1)));
    }

    public function test_un_pdf_con_demasiadas_paginas_da_un_error_claro(): void
    {
        // Un PDF de 12 páginas: se arma uniendo la del fixture las veces necesarias con pdfunite si existe.
        $unir = (new ExecutableFinder)->find('pdfunite');

        if ($unir === null) {
            $this->markTestSkipped('pdfunite no está instalado.');
        }

        $directorio = sys_get_temp_dir().'/prueba_paginas_'.bin2hex(random_bytes(4));
        mkdir($directorio);
        $partes = [];

        foreach (range(1, 6) as $i) {
            copy(base_path('tests/Fixtures/lectura/dos-paginas.pdf'), $partes[] = "{$directorio}/parte-{$i}.pdf");
        }

        $largo = "{$directorio}/largo.pdf";
        exec(escapeshellarg($unir).' '.implode(' ', array_map('escapeshellarg', $partes)).' '.escapeshellarg($largo));
        $contenido = (string) file_get_contents($largo);
        array_map('unlink', glob("{$directorio}/*"));
        rmdir($directorio);

        $archivo = $this->subir('largo.pdf', $contenido);
        $lectura = $this->lectura($archivo);

        try {
            app(PaginasDocumento::class)->generar($archivo, $lectura);
            $this->fail('Debió rechazar un PDF de 12 páginas.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('demasiadas páginas', $e->getMessage());
        }

        $this->assertSame(0, $lectura->fresh()->paginas);
        $this->assertSame([], Storage::disk(Documento::disco())->allFiles("lecturas/{$lectura->id}"));
        $this->assertSame([], glob(sys_get_temp_dir().'/lectura_*') ?: [], 'Quedó un directorio temporal.');
    }

    public function test_un_archivo_de_un_tipo_no_soportado_o_dañado_da_un_error_claro(): void
    {
        foreach (['notas.pdf' => 'esto no es un pdf', 'foto.png' => "\x89PNG\r\n\x1a\nbasura"] as $nombre => $contenido) {
            $archivo = $this->subir($nombre, $contenido);

            try {
                app(PaginasDocumento::class)->generar($archivo, $this->lectura($archivo));
                $this->fail("{$nombre} debió dar error.");
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString('basura', $e->getMessage(), 'El error no copia el contenido del archivo.');
            }
        }
    }

    public function test_una_foto_heic_sin_heif_convert_da_un_error_claro(): void
    {
        if ((new ExecutableFinder)->find('heif-convert') !== null) {
            $this->markTestSkipped('heif-convert sí está instalado: ver la prueba de conversión.');
        }

        // Un HEIC mínimo: solo trae la caja `ftyp` al principio.
        $archivo = $this->subir('foto.heic', "\0\0\0\x18ftypheic\0\0\0\0mif1heic");

        try {
            app(PaginasDocumento::class)->generar($archivo, $this->lectura($archivo));
            $this->fail('Debió exigir heif-convert.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('heif-convert', $e->getMessage());
            $this->assertStringContainsString('libheif-examples', $e->getMessage());
        }
    }

    public function test_una_foto_heic_se_convierte_cuando_heif_convert_esta_instalado(): void
    {
        $ejemplo = base_path('tests/Fixtures/lectura/foto.heic');

        if ((new ExecutableFinder)->find('heif-convert') === null || ! is_file($ejemplo)) {
            $this->markTestSkipped('Falta heif-convert o el HEIC de ejemplo (tests/Fixtures/lectura/foto.heic).');
        }

        $archivo = $this->subir('foto.heic', (string) file_get_contents($ejemplo));
        $lectura = $this->lectura($archivo);

        $this->assertSame(1, app(PaginasDocumento::class)->generar($archivo, $lectura));
        $this->assertStringStartsWith(self::PNG, app(PaginasDocumento::class)->leer($lectura, 1));
    }

    public function test_leer_una_pagina_que_no_existe_da_error(): void
    {
        $archivo = $this->subir('w2.pdf', $this->pdf());
        $lectura = $this->lectura($archivo);

        $this->expectException(RuntimeException::class);
        app(PaginasDocumento::class)->leer($lectura, 1);
    }

    public function test_releer_deja_solo_las_paginas_de_la_ultima_vez(): void
    {
        $archivo = $this->subir('w2.pdf', $this->pdf());
        $lectura = $this->lectura($archivo);
        $disco = Storage::disk(Documento::disco());

        app(PaginasDocumento::class)->generar($archivo, $lectura);
        $disco->put("lecturas/{$lectura->id}/pagina-9.png", 'sobrante de una lectura anterior');

        app(PaginasDocumento::class)->generar($archivo, $lectura);

        $this->assertCount(2, $disco->allFiles("lecturas/{$lectura->id}"));
    }
}
