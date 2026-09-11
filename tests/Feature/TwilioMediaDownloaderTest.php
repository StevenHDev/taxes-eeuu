<?php

namespace Tests\Feature;

use App\Services\Whatsapp\TwilioMediaDownloader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TwilioMediaDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.twilio.account_sid' => 'AC_test',
            'services.twilio.auth_token' => 'token_test',
        ]);
    }

    public function test_descarga_con_basic_auth_y_toma_el_mime_type_de_la_respuesta(): void
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        Http::fake([
            'https://api.twilio.com/media/0' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = app(TwilioMediaDownloader::class)->descargar('https://api.twilio.com/media/0');

        $this->assertSame('image/png', $resultado['mime_type']);
        $this->assertFileExists($resultado['ruta_local']);
        $this->assertStringEndsWith('.png', $resultado['ruta_local']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/media/0'
                && $request->hasHeader('Authorization');
        });

        unlink($resultado['ruta_local']);
    }

    public function test_recorta_el_charset_pegado_al_content_type(): void
    {
        Http::fake([
            'https://api.twilio.com/media/0' => Http::response('contenido', 200, ['Content-Type' => 'image/jpeg; charset=binary']),
        ]);

        $resultado = app(TwilioMediaDownloader::class)->descargar('https://api.twilio.com/media/0');

        $this->assertSame('image/jpeg', $resultado['mime_type']);

        unlink($resultado['ruta_local']);
    }

    public function test_lanza_una_excepcion_si_la_descarga_falla(): void
    {
        Http::fake(['https://api.twilio.com/media/0' => Http::response('no encontrado', 404)]);

        $this->expectException(RuntimeException::class);

        app(TwilioMediaDownloader::class)->descargar('https://api.twilio.com/media/0');
    }

    public function test_como_archivo_subido_envuelve_el_archivo_ya_descargado(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'wrap_test_');
        file_put_contents($ruta, 'contenido');

        $archivo = app(TwilioMediaDownloader::class)->comoArchivoSubido($ruta, 'application/pdf', 'w2.pdf');

        $this->assertInstanceOf(UploadedFile::class, $archivo);
        $this->assertSame('w2.pdf', $archivo->getClientOriginalName());
        $this->assertTrue($archivo->isValid());

        unlink($ruta);
    }
}
