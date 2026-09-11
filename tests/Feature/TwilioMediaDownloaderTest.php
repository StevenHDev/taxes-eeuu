<?php

namespace Tests\Feature;

use App\Enums\MetodoExtraccionDocumento;
use App\Services\Whatsapp\TwilioMediaDownloader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

    public function test_descarga_cada_media_del_payload_con_basic_auth_y_extrae_su_texto(): void
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        Http::fake([
            'https://api.twilio.com/media/0' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'W-2 transcrito']]],
            ], 200),
        ]);

        $payload = [
            'NumMedia' => '1',
            'MediaUrl0' => 'https://api.twilio.com/media/0',
            'MediaContentType0' => 'image/png',
        ];

        $resultados = app(TwilioMediaDownloader::class)->descargarYExtraer($payload);

        $this->assertCount(1, $resultados);
        $this->assertSame('W-2 transcrito', $resultados[0]['texto']);
        $this->assertSame(MetodoExtraccionDocumento::Vision, $resultados[0]['metodo']);
        $this->assertSame('image/png', $resultados[0]['mime_type']);
        $this->assertFileExists($resultados[0]['ruta_local']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/media/0'
                && $request->hasHeader('Authorization');
        });

        unlink($resultados[0]['ruta_local']);
    }

    public function test_ignora_urls_de_media_vacias_o_ausentes(): void
    {
        Http::fake();

        $resultados = app(TwilioMediaDownloader::class)->descargarYExtraer(['NumMedia' => '2']);

        $this->assertSame([], $resultados);
        Http::assertNothingSent();
    }

    public function test_lanza_una_excepcion_si_la_descarga_falla(): void
    {
        Http::fake(['https://api.twilio.com/media/0' => Http::response('no encontrado', 404)]);

        $this->expectException(\RuntimeException::class);

        app(TwilioMediaDownloader::class)->descargarYExtraer([
            'NumMedia' => '1',
            'MediaUrl0' => 'https://api.twilio.com/media/0',
            'MediaContentType0' => 'image/png',
        ]);
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
