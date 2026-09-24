<?php

namespace Tests\Feature;

use App\DataTransferObjects\AdjuntoWhatsapp;
use App\Enums\MetodoExtraccionDocumento;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\FormaCliente;
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
use Illuminate\Support\Facades\Storage;
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

        $resultado = $this->agente->responder(null, $this->historialCon('+15551234567', 'Jane Doe, jane@example.com'), $this->actor, [], '+15551234567');

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

    public function test_guardar_campo_cliente_con_modo_archivo_correlaciona_el_adjunto_por_referencia(): void
    {
        Storage::fake('s3');

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $rutaPdf = $this->crearPdfConTexto('W-2 de prueba con texto legible y suficiente longitud.');
        $adjunto = new AdjuntoWhatsapp(
            referencia: 'https://api.twilio.com/media/ME123',
            rutaLocal: $rutaPdf,
            mimeType: 'application/pdf',
            texto: 'W-2 de prueba con texto legible y suficiente longitud.',
            metodo: MetodoExtraccionDocumento::TextoPdf,
        );

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($this->respuestaOpenAi([
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_1', 'type' => 'function', 'function' => [
                            'name' => 'guardar_campo_cliente',
                            'arguments' => json_encode([
                                'forma' => 'form_1040',
                                'campo' => 'w2',
                                'tipo_campo' => 'documento',
                                'modo' => 'archivo',
                                'contenido' => $adjunto->referencia,
                            ]),
                        ]],
                    ],
                ]))
                ->push($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'Recibí tu W-2, gracias.'])),
        ]);

        $this->agente->responder($cliente, $this->historialCon('+15551234567', 'aquí está mi W-2'), $this->actor, [$adjunto]);

        $documento = Documento::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first();
        $this->assertNotNull($documento);
        $this->assertSame(MetodoExtraccionDocumento::TextoPdf, $documento->metodo_extraccion);

        unlink($rutaPdf);
    }

    public function test_guardar_campo_cliente_con_modo_archivo_sin_adjunto_correspondiente_no_adjunta_nada(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($this->respuestaOpenAi([
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_1', 'type' => 'function', 'function' => [
                            'name' => 'guardar_campo_cliente',
                            'arguments' => json_encode([
                                'forma' => 'form_1040',
                                'campo' => 'w2',
                                'tipo_campo' => 'documento',
                                'modo' => 'archivo',
                                'contenido' => 'referencia-que-no-existe',
                            ]),
                        ]],
                    ],
                ]))
                ->push($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'ok'])),
        ]);

        // Sin $adjuntos, resolverArchivo() no encuentra ningún match: el tool
        // call llega sin archivo, EventoValidator lo marca como error de
        // validación (recuperable) en vez de tronar con un TypeError dentro
        // de EventoRecoleccionService — ver App\Support\EventoValidator.
        $this->agente->responder($cliente, $this->historialCon('+15551234567', 'aquí está mi W-2'), $this->actor, []);

        $this->assertDatabaseCount('documentos', 0);
    }

    /**
     * Regresión de un caso real (2026-09-21, conversación con 3213445027): el
     * modelo no reprodujo la URL de media de Twilio carácter por carácter al
     * armar los argumentos de la tool call, el match exacto en
     * resolverArchivo() falló, y un Form 1095-A que sí se había subido y
     * extraído bien no quedó guardado. Con un único adjunto en el turno no
     * hay ambigüedad posible a la que recurrir — debe usarse igual.
     */
    public function test_guardar_campo_cliente_con_modo_archivo_usa_el_unico_adjunto_del_turno_aunque_la_referencia_no_coincida(): void
    {
        Storage::fake('s3');

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $rutaPdf = $this->crearPdfConTexto('Form 1095-A de prueba con texto legible y suficiente longitud.');
        $adjunto = new AdjuntoWhatsapp(
            referencia: 'https://api.twilio.com/2010-04-01/Accounts/AC.../Messages/MM.../Media/ME123',
            rutaLocal: $rutaPdf,
            mimeType: 'application/pdf',
            texto: 'Form 1095-A de prueba con texto legible y suficiente longitud.',
            metodo: MetodoExtraccionDocumento::TextoPdf,
        );

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($this->respuestaOpenAi([
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_1', 'type' => 'function', 'function' => [
                            'name' => 'guardar_campo_cliente',
                            'arguments' => json_encode([
                                'forma' => 'transversal',
                                'campo' => 'form_1095_a',
                                'tipo_campo' => 'documento',
                                'modo' => 'archivo',
                                // Referencia ligeramente distinta a la real —
                                // simula al modelo reproduciendo la URL con un
                                // error, como se vio en producción.
                                'contenido' => 'https://api.twilio.com/2010-04-01/Accounts/AC.../Messages/MM.../Media/ME124',
                            ]),
                        ]],
                    ],
                ]))
                ->push($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'Recibí el formulario, gracias.'])),
        ]);

        $this->agente->responder($cliente, $this->historialCon('+15551234567', 'aquí está mi 1095-A'), $this->actor, [$adjunto]);

        $documento = Documento::query()->where('user_id', $cliente->id)->where('campo', 'form_1095_a')->first();
        $this->assertNotNull($documento);

        unlink($rutaPdf);
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
}
