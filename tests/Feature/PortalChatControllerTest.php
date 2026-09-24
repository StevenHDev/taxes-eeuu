<?php

namespace Tests\Feature;

use App\Enums\MetodoExtraccionDocumento;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\PortalMensaje;
use App\Models\User;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'test-model']);
        $this->seed(AgentePromptsSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $respuesta
     */
    private function respuestaOpenAi(array $respuesta): array
    {
        return ['choices' => [['message' => $respuesta]]];
    }

    public function test_un_invitado_es_redirigido_a_login(): void
    {
        $this->get(route('portal.chat'))->assertRedirect(route('login'));
    }

    public function test_un_preparador_no_puede_entrar_al_portal_del_cliente(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('portal.chat'))->assertForbidden();
    }

    public function test_un_cliente_ve_su_chat_vacio_al_entrar(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)
            ->get(route('portal.chat'))
            ->assertInertia(fn ($page) => $page
                ->component('portal/chat')
                ->where('mensajes', []));
    }

    public function test_un_cliente_puede_enviar_un_mensaje_y_recibir_respuesta_del_agente(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi([
                'role' => 'assistant',
                'content' => '¿Para qué año fiscal es tu declaración?',
            ])),
        ]);

        $this->actingAs($cliente)
            ->from(route('portal.chat'))
            ->post(route('portal.chat.send'), ['contenido' => 'Hola, quiero empezar mis taxes'])
            ->assertRedirect(route('portal.chat'));

        $mensajes = PortalMensaje::query()->where('cliente_id', $cliente->id)->orderBy('id')->get();

        $this->assertCount(2, $mensajes);
        $this->assertSame(RolMensajeWhatsapp::Cliente, $mensajes[0]->rol);
        $this->assertSame('Hola, quiero empezar mis taxes', $mensajes[0]->contenido);
        $this->assertSame(RolMensajeWhatsapp::Agente, $mensajes[1]->rol);
        $this->assertSame('¿Para qué año fiscal es tu declaración?', $mensajes[1]->contenido);
    }

    public function test_sin_texto_ni_archivo_no_se_envia_nada(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)
            ->post(route('portal.chat.send'), ['contenido' => ''])
            ->assertSessionHasErrors('contenido');

        $this->assertDatabaseCount('portal_mensajes', 0);
    }

    /**
     * Mismo generador de PDF mínimo válido que AgenteConversacionalServiceTest.
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

        $ruta = tempnam(sys_get_temp_dir(), 'portal_chat_').'.pdf';
        file_put_contents($ruta, $pdf);

        return $ruta;
    }

    public function test_un_cliente_puede_adjuntar_un_documento_y_el_agente_lo_guarda(): void
    {
        Storage::fake('s3');

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $rutaPdf = $this->crearPdfConTexto('W-2 de prueba con texto legible y suficiente longitud.');
        $archivo = new UploadedFile($rutaPdf, 'w2.pdf', 'application/pdf', test: true);

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
                                // El controlador genera la referencia del adjunto
                                // (portal:<uuid>) — con un único adjunto en el
                                // turno, AgenteConversacionalService::resolverArchivo()
                                // lo usa igual aunque esta referencia no coincida.
                                'contenido' => 'referencia-que-no-coincide',
                            ]),
                        ]],
                    ],
                ]))
                ->push($this->respuestaOpenAi(['role' => 'assistant', 'content' => 'Recibí tu W-2, gracias.'])),
        ]);

        $this->actingAs($cliente)
            ->from(route('portal.chat'))
            ->post(route('portal.chat.send'), ['archivo' => $archivo])
            ->assertRedirect(route('portal.chat'));

        $documento = Documento::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first();
        $this->assertNotNull($documento);
        $this->assertSame(MetodoExtraccionDocumento::TextoPdf, $documento->metodo_extraccion);

        unlink($rutaPdf);
    }
}
