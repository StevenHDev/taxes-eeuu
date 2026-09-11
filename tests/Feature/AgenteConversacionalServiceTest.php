<?php

namespace Tests\Feature;

use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use App\Support\AgenteWhatsappUser;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgenteConversacionalServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgenteConversacionalService $agente;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'test-model']);
        $this->seed(AgentePromptsSeeder::class);

        $this->agente = app(AgenteConversacionalService::class);
        $this->actor = AgenteWhatsappUser::resolver();
    }

    /**
     * @return Collection<int, WhatsappMensaje>
     */
    private function historialCon(string $telefono, string $mensajeCliente): Collection
    {
        $mensaje = WhatsappMensaje::query()->create([
            'telefono' => $telefono,
            'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => $mensajeCliente,
            'mensaje_externo_id' => 'SM'.str_repeat('a', 32),
        ]);

        return collect([$mensaje]);
    }

    /**
     * @param  array<string, mixed>  $respuesta
     */
    private function respuestaOpenAi(array $respuesta): array
    {
        return ['choices' => [['message' => $respuesta]]];
    }

    public function test_devuelve_el_texto_final_cuando_no_hay_tool_calls(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi([
                'role' => 'assistant',
                'content' => '¿Ya tienes una cuenta creada en GlobalTax?',
            ])),
        ]);

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'Hola'), $this->actor);

        $this->assertSame('¿Ya tienes una cuenta creada en GlobalTax?', $resultado['texto']);
        $this->assertSame(1, $resultado['prompt_version']);
        $this->assertNull($resultado['cliente']);

        Http::assertSentCount(1);
    }

    public function test_ejecuta_un_tool_call_y_sigue_hasta_obtener_texto_final(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($this->respuestaOpenAi([
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'think', 'arguments' => '{"razonamiento":"x"}']],
                    ],
                ]))
                ->push($this->respuestaOpenAi([
                    'role' => 'assistant',
                    'content' => 'Perfecto, ¿me confirmas tu nombre?',
                ])),
        ]);

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'No tengo cuenta'), $this->actor);

        $this->assertSame('Perfecto, ¿me confirmas tu nombre?', $resultado['texto']);
        Http::assertSentCount(2);
    }

    public function test_crear_cliente_taxes_actualiza_el_cliente_devuelto(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($this->respuestaOpenAi([
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_1', 'type' => 'function', 'function' => [
                            'name' => 'crear_cliente_taxes',
                            'arguments' => json_encode(['nombre' => 'Jane Doe', 'email' => 'jane@example.com']),
                        ]],
                    ],
                ]))
                ->push($this->respuestaOpenAi([
                    'role' => 'assistant',
                    'content' => '¿Para qué año fiscal es tu declaración?',
                ])),
        ]);

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'Jane Doe, jane@example.com'), $this->actor);

        $this->assertNotNull($resultado['cliente']);
        $this->assertSame('Jane Doe', $resultado['cliente']->name);
        $this->assertSame(UserRole::Client, $resultado['cliente']->role);
        // El teléfono se deriva del historial, no de un argumento del modelo
        // — así la cuenta recién creada queda vinculada por teléfono desde ya
        // (ver ToolExecutor::crearClienteTaxes), sin depender de que alguien
        // lo escriba a mano después en /usuarios.
        $this->assertSame('+15551234567', $resultado['cliente']->phone);
    }

    public function test_recalcula_la_fase_entre_tool_calls_dentro_del_mismo_turno(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($this->respuestaOpenAi([
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_1', 'type' => 'function', 'function' => [
                            'name' => 'declarar_formas_cliente',
                            'arguments' => json_encode(['tax_year' => 2025, 'formas_aplicables' => ['form_990']]),
                        ]],
                    ],
                ]))
                ->push($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'Ya casi, ¿me confirmas tu SSN o ITIN?'])),
        ]);

        $this->agente->responder($cliente, $this->historialCon('+15551234567', 'Soy una organización sin fines de lucro'), $this->actor);

        Http::assertSentCount(2);

        // La 2da llamada a OpenAI (después de declarar_formas_cliente) ya debe
        // exponer las tools de Recoleccion, no las de DeterminacionFormas.
        Http::assertSent(function ($request) {
            $nombres = collect($request['tools'] ?? [])->pluck('function.name')->all();

            return in_array('consultar_pendientes_cliente', $nombres, true);
        });
    }

    public function test_corta_en_el_tope_de_iteraciones_si_el_modelo_nunca_da_texto_final(): void
    {
        $siempreThink = $this->respuestaOpenAi([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'think', 'arguments' => '{"razonamiento":"x"}']],
            ],
        ]);

        Http::fake(['api.openai.com/*' => Http::response($siempreThink)]);

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'Hola'), $this->actor);

        $this->assertNotSame('', $resultado['texto']);
        Http::assertSentCount(8);
    }

    public function test_mapea_el_historial_con_los_roles_correctos_para_openai(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'ok'])),
        ]);

        $telefono = '+15551234567';
        WhatsappMensaje::query()->create(['telefono' => $telefono, 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'primer mensaje', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);
        WhatsappMensaje::query()->create(['telefono' => $telefono, 'rol' => RolMensajeWhatsapp::Agente, 'contenido' => 'respuesta del agente']);
        $historial = WhatsappMensaje::query()->where('telefono', $telefono)->orderBy('id')->get();

        $this->agente->responder(null, $historial, $this->actor);

        Http::assertSent(function ($request) {
            $mensajes = $request['messages'];

            return $mensajes[1]['role'] === 'user' && $mensajes[1]['content'] === 'primer mensaje'
                && $mensajes[2]['role'] === 'assistant' && $mensajes[2]['content'] === 'respuesta del agente';
        });
    }
}
