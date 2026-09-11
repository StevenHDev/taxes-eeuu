<?php

namespace Tests\Feature;

use App\Services\Whatsapp\Meta\MetaChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MetaChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.access_token' => 'test-access-token',
            'services.meta.phone_number_id' => '1234567890',
            'services.meta.api_version' => 'v21.0',
        ]);
    }

    public function test_descargar_media_resuelve_la_url_temporal_y_descarga_el_archivo(): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/wamid.MEDIA1' => Http::response([
                'url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/xyz',
                'mime_type' => 'image/jpeg',
            ], 200),
            'https://lookaside.fbsbx.com/*' => Http::response('contenido-binario', 200),
        ]);

        $resultado = app(MetaChannel::class)->descargarMedia('wamid.MEDIA1');

        $this->assertSame('image/jpeg', $resultado['mime_type']);
        $this->assertFileExists($resultado['ruta_local']);
        $this->assertSame('contenido-binario', file_get_contents($resultado['ruta_local']));

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-access-token'));

        unlink($resultado['ruta_local']);
    }

    public function test_descargar_media_lanza_excepcion_si_no_se_puede_resolver_la_url(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response('no encontrado', 404)]);

        $this->expectException(RuntimeException::class);

        app(MetaChannel::class)->descargarMedia('wamid.MEDIA1');
    }

    public function test_enviar_texto_lanza_excepcion_si_la_api_de_meta_falla(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'invalid token']], 401)]);

        $this->expectException(RuntimeException::class);

        app(MetaChannel::class)->enviarTexto('+15551234567', 'hola');
    }

    public function test_enviar_texto_manda_el_payload_correcto_a_la_graph_api(): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/1234567890/messages' => Http::response(['messages' => [['id' => 'wamid.OUT1']]], 200),
        ]);

        $id = app(MetaChannel::class)->enviarTexto('+15551234567', 'Hola, gracias por tu mensaje.');

        $this->assertSame('wamid.OUT1', $id);

        Http::assertSent(fn ($request) => $request['to'] === '15551234567'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Hola, gracias por tu mensaje.');
    }
}
