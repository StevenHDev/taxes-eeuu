<?php

namespace Tests\Feature;

use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Notifications\BienvenidaClientePortal;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use App\Support\AgenteWhatsappUser;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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
        Notification::fake();

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

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'Jane Doe, jane@example.com'), $this->actor, '+15551234567');

        $this->assertNotNull($resultado['cliente']);
        $this->assertSame('Jane Doe', $resultado['cliente']->name);
        $this->assertSame(UserRole::Client, $resultado['cliente']->role);
        // El teléfono se pasa explícito (ver ProcesarMensajeWhatsappJob) — así
        // la cuenta recién creada queda vinculada por teléfono desde ya (ver
        // ToolExecutor::crearClienteTaxes), sin depender de que alguien lo
        // escriba a mano después en /usuarios.
        $this->assertSame('+15551234567', $resultado['cliente']->phone);
        // La cuenta se crea con una contraseña aleatoria que nadie conoce —
        // sin este aviso, el cliente nunca podría entrar al portal seguro.
        Notification::assertSentTo($resultado['cliente'], BienvenidaClientePortal::class);
    }

    public function test_recalcula_la_fase_entre_tool_calls_dentro_del_mismo_turno(): void
    {
        Notification::fake();

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
                ->push($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'Listo, ya tienes cuenta.'])),
        ]);

        $this->agente->responder(null, $this->historialCon('+15551234567', 'Jane Doe, jane@example.com'), $this->actor, '+15551234567');

        Http::assertSentCount(2);

        // La 2da llamada (después de crear la cuenta) ya es de la fase
        // Consentimiento, no de VerificacionCuenta.
        Http::assertSent(function ($request) {
            $nombres = collect($request['tools'] ?? [])->pluck('function.name')->all();

            return in_array('enviar_enlace_activacion', $nombres, true);
        });
    }

    public function test_corta_en_el_tope_de_iteraciones_y_fuerza_una_respuesta_de_texto_real(): void
    {
        $siempreThink = $this->respuestaOpenAi([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'think', 'arguments' => '{"razonamiento":"x"}']],
            ],
        ]);

        $secuencia = Http::sequence();
        // 20 vueltas (MAX_ITERACIONES, subido de 8 a 20 el 2026-09-21 —
        // ver AgenteConversacionalService: 8 ya no alcanza con ACTIVOS en
        // 44 pasos) agotan el tope sin texto final.
        for ($i = 0; $i < 20; $i++) {
            $secuencia->push($siempreThink);
        }
        $secuencia->push($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'Perdona la demora, ¿en qué te ayudo?']));

        Http::fake(['api.openai.com/*' => $secuencia]);

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'Hola'), $this->actor);

        // Las 20 vueltas agotan MAX_ITERACIONES sin texto final; la 21ra
        // llamada (sin tools) fuerza un cierre real en vez del mensaje fijo
        // "Dame un momento, ya te respondo." que antes dejaba el turno
        // colgado sin que nada volviera a responderle al cliente.
        $this->assertSame('Perdona la demora, ¿en qué te ayudo?', $resultado['texto']);
        Http::assertSentCount(21);
        Http::assertSent(fn ($request) => ! array_key_exists('tools', $request->data()));
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

    /**
     * Mismo generador de PDF mínimo válido que PdfTextExtractorServiceTest.
     */
    private function crearPdfConTexto(string $texto): string
    {
        $streamContenido = "BT /F1 12 Tf 20 700 Td ({$texto}) Tj ET";

        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Length '.strlen($streamContenido).' >>'."\nstream\n{$streamContenido}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objetos as $numero => $cuerpo) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$cuerpo}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        $ruta = tempnam(sys_get_temp_dir(), 'agente_adjunto_').'.pdf';
        file_put_contents($ruta, $pdf);

        return $ruta;
    }

    /**
     * Regresión de un caso real (2026-09-21, conversación con 3213445027): el
     * mensaje final del agente llegó a incluirle al cliente el razonamiento
     * interno como un bloque JSON — con el mismo shape que los argumentos de
     * la tool `think` — en vez de invocarla como tool call. El razonamiento
     * nunca puede llegarle al cliente, así que se filtra en código.
     */
    public function test_filtra_el_razonamiento_si_el_modelo_lo_escribe_como_json_en_el_mensaje_final(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi([
                'role' => 'assistant',
                'content' => '{"razonamiento":"Ahora tengo la entrada exacta para form_1095_a. Continúo con la solicitud del documento confirmado."}'
                    ."\nPor favor, sube tu Form 1095-A en PDF, JPG, JPEG, PNG o HEIC.",
            ])),
        ]);

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'si'), $this->actor);

        $this->assertSame('Por favor, sube tu Form 1095-A en PDF, JPG, JPEG, PNG o HEIC.', $resultado['texto']);
    }

    public function test_no_toca_un_mensaje_final_que_simplemente_empieza_con_una_llave_de_texto(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi([
                'role' => 'assistant',
                'content' => '{esto no es JSON, es solo texto que arranca con una llave} ¿me confirmas tu nombre?',
            ])),
        ]);

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'Hola'), $this->actor);

        $this->assertSame('{esto no es JSON, es solo texto que arranca con una llave} ¿me confirmas tu nombre?', $resultado['texto']);
    }

    /**
     * Regresión de un caso real (2026-09-21, conversación con 3213445027):
     * una racha de más de diez respuestas del cliente ("no", "si", etc.) se
     * perdió, una detrás de otra, porque el modelo respondía directo con
     * texto sin invocar NINGUNA tool en ese turno — ni siquiera think, mucho
     * menos guardar_campo_cliente. La primera llamada de cada turno ahora
     * fuerza tool_choice="required" para que eso no pueda volver a pasar; la
     * segunda (si hace falta encadenar más de una tool) vuelve a "auto".
     */
    public function test_la_primera_llamada_del_turno_fuerza_tool_choice_required_y_las_siguientes_no(): void
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
                ->push($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'Listo.'])),
        ]);

        $this->agente->responder(null, $this->historialCon('+15551234567', 'Hola'), $this->actor);

        $peticiones = Http::recorded();
        $this->assertCount(2, $peticiones);
        $this->assertSame('required', $peticiones[0][0]['tool_choice']);
        $this->assertFalse(array_key_exists('tool_choice', $peticiones[1][0]->data()));
    }

    /**
     * F2.4: a un cliente sin contraseña y sin consentimiento, el enlace que
     * el código agrega en la fase Consentimiento es el de activación.
     */
    public function test_consentimiento_manda_el_enlace_de_activacion_a_un_cliente_sin_contrasena(): void
    {
        $telefono = '+15559990002';
        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => $telefono, 'es_prueba' => true]);
        $cliente->forceFill(['contrasena_definida_en' => null])->save();

        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'Listo'])),
        ]);

        $this->agente->responder($cliente, $this->historialCon($telefono, 'Hola'), $this->actor, $telefono);

        $cuerpo = Http::recorded()->first()[0]->data();
        $nota = collect($cuerpo['messages'])->last(fn (array $m) => $m['role'] === 'system');

        $this->assertStringContainsString(url('/activar/'.$cliente->id), $nota['content']);
        $this->assertStringContainsString('signature=', $nota['content']);
        $this->assertContains('enviar_enlace_activacion', collect($cuerpo['tools'])->pluck('function.name')->all());
    }
}
