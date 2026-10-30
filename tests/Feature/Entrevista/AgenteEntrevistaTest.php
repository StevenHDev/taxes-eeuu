<?php

namespace Tests\Feature\Entrevista;

use App\DataTransferObjects\MensajeEntranteWhatsapp;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\FaseConversacion;
use App\Enums\UserRole;
use App\Jobs\ProcesarMensajeWhatsappJob;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Whatsapp\WhatsappChannel;
use App\Services\WhatsappAgent\AdjuntosWhatsappService;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use App\Services\WhatsappAgent\EstadoConversacionResolver;
use App\Services\WhatsappAgent\ToolDefinitions;
use App\Services\WhatsappAgent\ToolExecutor;
use App\Support\AgenteWhatsappUser;
use App\Support\RedactorDatosSensibles;
use Database\Seeders\AgentePromptsSeeder;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * F2.8: el agente de WhatsApp sobre la entrevista nueva.
 */
class AgenteEntrevistaTest extends TestCase
{
    use RefreshDatabase;

    private const TELEFONO = '+15550004444';

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'test-model']);
        $this->seed(CatalogoFiscalSeeder::class);
        $this->seed(AgentePromptsSeeder::class);
        app(ConsentimientosCargador::class)->cargar();

        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true, 'phone' => self::TELEFONO]);
        $this->cliente->forceFill(['entrevista_catalogo' => true])->save();
    }

    private function firmar(?User $cliente = null): void
    {
        app(Consentimientos::class)->firmar($cliente ?? $this->cliente, (int) config('tax.current_tax_year'), 1, 'Cliente', null, null);
    }

    private function fase(?User $cliente = null, bool $portal = false): FaseConversacion
    {
        return app(EstadoConversacionResolver::class)->resolver(($cliente ?? $this->cliente)->fresh(), $portal);
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(string $nombre, array $argumentos = []): array
    {
        return app(ToolExecutor::class)->ejecutar($nombre, $argumentos, $this->cliente->fresh(), AgenteWhatsappUser::resolver(), telefono: self::TELEFONO);
    }

    public function test_el_resolver_da_cada_fase_de_la_entrevista_nueva(): void
    {
        $this->assertSame(FaseConversacion::Consentimiento, $this->fase());

        $this->firmar();
        $this->assertSame(FaseConversacion::Entrevista, $this->fase());
        $this->assertSame(FaseConversacion::PortalDudas, $this->fase(portal: true));

        $real = User::factory()->create(['role' => UserRole::Client]);
        $real->forceFill(['entrevista_catalogo' => true])->save();
        $this->firmar($real);
        $this->assertSame(FaseConversacion::Seguimiento, $this->fase($real), 'Cliente real: entrevista por WhatsApp apagada hasta L3.');

        config(['tax.entrevista_whatsapp' => true]);
        $this->assertSame(FaseConversacion::Entrevista, $this->fase($real));

        $viejo = User::factory()->create(['role' => UserRole::Client]);
        $this->assertSame(FaseConversacion::DeterminacionFormas, $this->fase($viejo), 'Sin la bandera sigue el flujo actual.');
    }

    public function test_sin_preguntas_no_sensibles_por_responder_la_fase_es_seguimiento(): void
    {
        $this->firmar();
        $guardador = app(RespuestaGuardador::class);
        $entrevista = $guardador->entrevistaDe($this->cliente, CanalRespuesta::Portal);

        // Las respuestas abren seguimientos: se repite hasta que no quede nada.
        for ($vuelta = 0; $vuelta < 5; $vuelta++) {
            foreach (app(MotorEntrevista::class)->situacion($entrevista->fresh())->pendientesParaWhatsapp() as $p) {
                $tipo = app(CatalogoFiscal::class)->preguntas(2025)->firstWhere('codigo', $p['codigo']);
                $valor = match ($tipo['tipo_respuesta']) {
                    'opciones' => $tipo['opciones'][0]['valor'],
                    'numero' => 1,
                    'fecha' => '1990-01-01',
                    'dato' => 'x',
                    default => 'no',
                };
                $guardador->guardar($entrevista->fresh(), new RespuestaData($p['codigo'], $valor, $p['persona_id']), CanalRespuesta::Portal, $this->cliente);
            }
        }

        $this->assertSame(FaseConversacion::Seguimiento, $this->fase());
    }

    public function test_guardar_respuestas_guarda_varias_con_canal_whatsapp_y_rechaza_lo_sensible_y_lo_no_visible(): void
    {
        $this->firmar();

        $resultado = $this->tool('guardar_respuestas', ['respuestas' => [
            ['codigo' => 'D1', 'valor' => 'si'],
            ['codigo' => 'D1.1', 'valor' => 2],
            ['codigo' => 'D16', 'valor' => 'si'],
            ['codigo' => 'A3', 'valor' => 'ssn_valido'],
            ['codigo' => 'G3', 'valor' => 'deposito_directo'],
            ['codigo' => 'B2', 'valor' => 'si'],
        ]]);

        $this->assertSame(['D1', 'D1.1', 'D16'], $resultado['guardadas']);
        $this->assertSame(['A3', 'G3', 'B2'], array_column($resultado['rechazadas'], 'codigo'));
        $this->assertSame(3, EntrevistaRespuesta::query()->count());
        $this->assertSame([CanalRespuesta::Whatsapp], EntrevistaRespuesta::query()->get()->pluck('canal')->unique()->values()->all());
        $this->assertSame(AgenteWhatsappUser::resolver()->id, EntrevistaRespuesta::query()->value('actor_id'));
    }

    public function test_consultar_entrevista_no_trae_lo_sensible_y_da_los_enlaces(): void
    {
        $this->firmar();

        $resultado = $this->tool('consultar_entrevista');
        $codigos = array_column($resultado['proximas_preguntas'], 'codigo');

        $this->assertContains('B1', $codigos);
        $this->assertNotContains('A3', $codigos);
        $this->assertNotContains('A1', $codigos, 'La pantalla 1 va por el portal.');
        $this->assertSame(route('portal.entrevista.documentos'), $resultado['enlaces']['documentos']);
        $this->assertGreaterThan(0, $resultado['falta_en_el_portal']['preguntas_sensibles']);
    }

    public function test_con_la_entrevista_por_whatsapp_apagada_nunca_se_ofrece_guardar_respuestas(): void
    {
        $real = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15550005555']);
        $real->forceFill(['entrevista_catalogo' => true])->save();
        $this->firmar($real);

        $fase = $this->fase($real);
        $this->assertNotContains('guardar_respuestas', collect(ToolDefinitions::habilitadasParaFase($fase))->pluck('function.name')->all());

        $resultado = app(ToolExecutor::class)->ejecutar('guardar_respuestas', ['respuestas' => [['codigo' => 'D1', 'valor' => 'si']]], $real, AgenteWhatsappUser::resolver());
        $this->assertArrayHasKey('error', $resultado);
        $this->assertSame(0, EntrevistaRespuesta::query()->count());
    }

    private function procesar(string $texto, array $media = []): void
    {
        $canal = Mockery::mock(WhatsappChannel::class);
        $canal->shouldReceive('enviarTexto')->andReturn('SM'.uniqid());
        $this->app->instance(WhatsappChannel::class, $canal);

        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Eso va en el portal.']]]])]);

        (new ProcesarMensajeWhatsappJob(new MensajeEntranteWhatsapp('twilio', 'SM'.uniqid(), self::TELEFONO, $texto, $media)))
            ->handle(app(AgenteConversacionalService::class), $canal, app(AdjuntosWhatsappService::class));
    }

    public function test_un_archivo_por_whatsapp_no_se_descarga_ni_se_guarda(): void
    {
        $this->firmar();
        $adjuntos = Mockery::mock(AdjuntosWhatsappService::class);
        $adjuntos->shouldNotReceive('resolver');
        $this->app->instance(AdjuntosWhatsappService::class, $adjuntos);

        $this->procesar('aquí va mi W-2', ['https://api.twilio.com/media/123']);

        $guardado = WhatsappMensaje::query()->where('rol', 'cliente')->sole()->contenido;
        $this->assertStringContainsString(ProcesarMensajeWhatsappJob::ARCHIVO_NO_GUARDADO, $guardado);
        $this->assertStringNotContainsString('twilio.com', json_encode(Http::recorded()->map(fn ($r) => $r[0]->data())->all()));
    }

    public function test_un_ssn_escrito_se_oculta_antes_de_guardarlo_y_de_mandarlo_a_openai(): void
    {
        $this->firmar();

        $this->procesar('mi ssn es 123-45-6789');

        $this->assertSame('mi ssn es '.RedactorDatosSensibles::MARCA, WhatsappMensaje::query()->where('rol', 'cliente')->sole()->contenido);
        $this->assertStringNotContainsString('6789', json_encode(Http::recorded()->map(fn ($r) => $r[0]->data())->all()));
    }
}
