<?php

namespace Tests\Feature;

use App\Services\WhatsappAgent\OpenAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'test-model',
            'services.openai.vision_model' => 'test-vision-model',
            'services.openai.retries' => 2,
            'services.openai.retry_backoff_ms' => 1,
        ]);
    }

    public function test_devuelve_el_mensaje_del_asistente_y_manda_el_modelo_configurado_por_default(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Hola, ¿en qué te ayudo?']],
                ],
            ], 200),
        ]);

        $client = new OpenAiClient;

        $mensaje = $client->completarChat(
            mensajes: [['role' => 'user', 'content' => 'hola']],
            tools: [],
        );

        $this->assertSame('Hola, ¿en qué te ayudo?', $mensaje['content']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['model'] === 'test-model'
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    public function test_usa_el_modelo_explicito_cuando_se_indica(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]],
            ], 200),
        ]);

        (new OpenAiClient)->completarChat([], [], modelo: 'otro-modelo');

        Http::assertSent(fn ($request) => $request['model'] === 'otro-modelo');
    }

    public function test_incluye_las_tool_calls_del_mensaje_cuando_vienen(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'think', 'arguments' => '{}']],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $mensaje = (new OpenAiClient)->completarChat([], []);

        $this->assertSame('call_1', $mensaje['tool_calls'][0]['id']);
    }

    public function test_reintenta_ante_un_error_transitorio_y_termina_en_exito(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['error' => 'server error'], 500)
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'listo']]]], 200),
        ]);

        $mensaje = (new OpenAiClient)->completarChat([], []);

        $this->assertSame('listo', $mensaje['content']);
    }

    public function test_lanza_una_excepcion_si_la_api_falla_de_forma_persistente(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'nope'], 500)]);

        $this->expectException(RuntimeException::class);

        (new OpenAiClient)->completarChat([], []);
    }

    public function test_transcribir_imagen_manda_el_modelo_de_vision_y_el_content_como_image_url(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'W-2 transcrito tal cual']]],
            ], 200),
        ]);

        $texto = (new OpenAiClient)->transcribirImagen('Transcribe todo lo visible.', 'data:image/png;base64,AAAA');

        $this->assertSame('W-2 transcrito tal cual', $texto);

        Http::assertSent(function ($request) {
            $contenido = $request['messages'][0]['content'];

            return $request['model'] === 'test-vision-model'
                && $contenido[0]['type'] === 'text'
                && $contenido[1]['type'] === 'image_url'
                && $contenido[1]['image_url']['url'] === 'data:image/png;base64,AAAA';
        });
    }

    public function test_lanza_una_excepcion_si_la_respuesta_no_trae_choices(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['foo' => 'bar'], 200)]);

        $this->expectException(RuntimeException::class);

        (new OpenAiClient)->completarChat([], []);
    }

    /**
     * Bug real reportado en producción: al cambiar a gpt-5.6-luna, cada
     * turno con tools fallaba con 400 ("Function tools with
     * reasoning_effort are not supported... set reasoning_effort to
     * 'none'") — probado directo contra la API real. Un modelo NO
     * razonador (ej. gpt-4.1-mini) hace lo contrario: rechaza este
     * parámetro si no lo espera, así que solo debe mandarse cuando el
     * modelo en uso es de razonamiento Y la llamada trae tools.
     */
    private function unTool(): array
    {
        return [['type' => 'function', 'function' => ['name' => 'think', 'parameters' => ['type' => 'object']]]];
    }

    public function test_no_manda_reasoning_effort_si_no_hay_tools(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]], 200)]);

        (new OpenAiClient)->completarChat([], [], modelo: 'gpt-5.6-luna');

        Http::assertSent(fn ($request) => ! array_key_exists('reasoning_effort', $request->data()));
    }

    public function test_no_manda_reasoning_effort_para_un_modelo_no_razonador_aunque_haya_tools(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]], 200)]);

        (new OpenAiClient)->completarChat([], $this->unTool(), modelo: 'gpt-4.1-mini');

        Http::assertSent(fn ($request) => ! array_key_exists('reasoning_effort', $request->data()));
    }

    public function test_manda_reasoning_effort_none_para_un_modelo_de_razonamiento_con_tools(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]], 200)]);

        (new OpenAiClient)->completarChat([], $this->unTool(), modelo: 'gpt-5.6-luna');

        Http::assertSent(fn ($request) => $request['reasoning_effort'] === 'none');
    }

    public function test_manda_reasoning_effort_none_tambien_para_la_familia_o(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]], 200)]);

        (new OpenAiClient)->completarChat([], $this->unTool(), modelo: 'o4-mini');

        Http::assertSent(fn ($request) => $request['reasoning_effort'] === 'none');
    }

    public function test_manda_tool_choice_cuando_se_indica_y_hay_tools(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]], 200)]);

        (new OpenAiClient)->completarChat([], $this->unTool(), toolChoice: 'required');

        Http::assertSent(fn ($request) => $request['tool_choice'] === 'required');
    }

    /**
     * La API rechaza tool_choice sin tools en la misma llamada — nunca debe
     * mandarse ninguno de los dos cuando $tools está vacío, aunque se pida
     * explícitamente (ver AgenteConversacionalService, llamada de cierre
     * forzado, que corre sin tools).
     */
    public function test_no_manda_tool_choice_si_no_hay_tools_aunque_se_pida(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]], 200)]);

        (new OpenAiClient)->completarChat([], [], toolChoice: 'required');

        Http::assertSent(fn ($request) => ! array_key_exists('tool_choice', $request->data()));
    }

    public function test_no_manda_tool_choice_si_no_se_indica(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]], 200)]);

        (new OpenAiClient)->completarChat([], $this->unTool());

        Http::assertSent(fn ($request) => ! array_key_exists('tool_choice', $request->data()));
    }
}
