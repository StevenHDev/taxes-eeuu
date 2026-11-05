<?php

namespace Tests\Feature\Entrevista;

use App\DataTransferObjects\MensajeEntranteWhatsapp;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\FaseConversacion;
use App\Enums\UserRole;
use App\Jobs\ContinuarPorWhatsappTrasConsentimientoJob;
use App\Jobs\ProcesarMensajeWhatsappJob;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Whatsapp\WhatsappChannel;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use App\Services\WhatsappAgent\EstadoConversacionResolver;
use App\Services\WhatsappAgent\ToolDefinitions;
use App\Services\WhatsappAgent\ToolExecutor;
use App\Support\AgenteWhatsappUser;
use App\Support\RedactorDatosSensibles;
use Database\Seeders\AgentePromptsSeeder;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        $this->firmar($real);
        $this->assertSame(FaseConversacion::Seguimiento, $this->fase($real), 'Cliente real: entrevista por WhatsApp apagada hasta L3.');

        config(['tax.entrevista_whatsapp' => true]);
        $this->assertSame(FaseConversacion::Entrevista, $this->fase($real));
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

    public function test_por_whatsapp_no_se_guarda_nada_de_la_pantalla_de_identidad_aunque_no_sea_sensible(): void
    {
        $this->firmar();

        $resultado = $this->tool('guardar_respuestas', ['respuestas' => [
            ['codigo' => 'A1', 'valor' => 'nunca casado'],
            ['codigo' => 'A2', 'valor' => 'no'],
            ['codigo' => 'B1', 'valor' => 'nunca_casado'],
        ]]);

        $this->assertSame(['B1'], $resultado['guardadas']);
        $this->assertSame(['A1', 'A2'], array_column($resultado['rechazadas'], 'codigo'));
        $this->assertSame(0, EntrevistaRespuesta::query()->whereIn('pregunta_codigo', ['A1', 'A2'])->count());
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
        $this->assertFalse($resultado['con_conyuge']);

        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'B1', 'valor' => 'casado']]]);
        $this->assertTrue($this->tool('consultar_entrevista')['con_conyuge']);
    }

    public function test_consultar_entrevista_trae_en_que_va_la_declaracion_sin_montos(): void
    {
        $this->firmar();
        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'B1', 'valor' => 'casado']]]);
        $this->assertArrayHasKey('estado', $this->tool('consultar_entrevista')['expediente']);

        $expediente = Expediente::query()->where('user_id', $this->cliente->id)->firstOrFail();
        $expediente->update(['estado' => EstadoExpediente::Presentado, 'presentado_en' => '2026-03-10', 'estado_desde' => '2026-03-10 09:00:00']);

        $resultado = $this->tool('consultar_entrevista');

        $this->assertSame('presentado', $resultado['expediente']['estado']);
        $this->assertStringContainsString('Presentamos tu declaración al IRS el', $resultado['expediente']['para_el_cliente']);
        $this->assertStringContainsString('2026', $resultado['expediente']['para_el_cliente']);
        $this->assertNotNull($resultado['expediente']['desde']);
        $this->assertSame(['estado', 'para_el_cliente', 'desde'], array_keys($resultado['expediente']));
        $this->assertDoesNotMatchRegularExpression('/\$\s?\d|refund|reembolso de/i', json_encode($resultado['expediente'], JSON_UNESCAPED_UNICODE));
    }

    public function test_el_texto_del_estado_para_el_agente_sale_en_espanol_aunque_el_portal_este_en_ingles(): void
    {
        $this->firmar();
        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'B1', 'valor' => 'casado']]]);
        Expediente::query()->where('user_id', $this->cliente->id)->update(['estado' => EstadoExpediente::Aceptado]);

        app()->setLocale('en');

        $this->assertSame('El IRS aceptó tu declaración.', $this->tool('consultar_entrevista')['expediente']['para_el_cliente']);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_consultar_entrevista_da_como_maximo_8_preguntas_de_una_sola_pantalla_y_los_seguimientos_primero(): void
    {
        $this->firmar();

        $resultado = $this->tool('consultar_entrevista');

        $this->assertLessThanOrEqual(8, count($resultado['proximas_preguntas']));
        $this->assertCount(1, array_unique(array_column($resultado['proximas_preguntas'], 'pantalla')), 'Cada mensaje es de un solo tema.');
        $this->assertGreaterThan(0, $resultado['quedan_mas'], 'Quedan más preguntas, de esta y de otras pantallas.');

        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'D1', 'valor' => 'si']]]);

        $despues = $this->tool('consultar_entrevista')['proximas_preguntas'];

        $this->assertSame('D1.1', $despues[0]['codigo'], 'Lo que abrió su "sí" va primero.');
        $this->assertCount(1, array_unique(array_column($despues, 'pantalla')));
    }

    public function test_consultar_entrevista_trae_la_ayuda_de_cada_pregunta(): void
    {
        $this->firmar();
        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'B1', 'valor' => 'nunca_casado'], ['codigo' => 'B5', 'valor' => 'si']]]);

        $ayudas = [];

        for ($vuelta = 0; $vuelta < 8; $vuelta++) {
            $preguntas = $this->tool('consultar_entrevista')['proximas_preguntas'];

            foreach ($preguntas as $p) {
                $ayudas[$p['codigo']] = $p['ayuda'];
            }

            if (isset($ayudas['D15'])) {
                break;
            }

            $this->tool('guardar_respuestas', ['respuestas' => array_map(fn (array $p) => ['codigo' => $p['codigo'], 'valor' => $p['tipo'] === 'opciones' ? $p['opciones'][0] : 'no'], array_values(array_filter($preguntas, fn (array $p) => in_array($p['tipo'], ['si_no', 'usted_conyuge_no'], true))))]);
        }

        $this->assertStringContainsString('salario', (string) $ayudas['D15'], 'El trabajo por cuenta propia aclara que no incluye el salario.');
    }

    public function test_consultar_entrevista_trae_que_subir_pero_no_la_nota_del_cliente(): void
    {
        $this->firmar();
        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'D12', 'valor' => 'si']]]);
        $entrevista = Entrevista::query()->where('user_id', $this->cliente->id)->firstOrFail();
        $entrevista->documentos()->where('documento_codigo', 'registros-alquiler')->firstOrFail()->update(['nota_cliente' => 'Vacía en marzo.']);

        $pendientes = $this->tool('consultar_entrevista')['documentos']['pendientes'];
        $alquiler = collect($pendientes)->firstWhere('nombre', 'Registros de alquiler');

        $this->assertNotNull($alquiler);
        $this->assertStringContainsString('No tienes que sumar nada', $alquiler['que_subir']);
        $this->assertArrayNotHasKey('nota_cliente', $alquiler);
        $this->assertStringNotContainsString('Vacía en marzo.', json_encode($pendientes));
        $this->assertStringNotContainsString('plantillas/', json_encode($pendientes));
    }

    public function test_consultar_entrevista_marca_el_documento_que_no_se_pudo_leer_sin_datos_leidos(): void
    {
        $this->firmar();
        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'D1', 'valor' => 'si']]]);
        $entrevista = Entrevista::query()->where('user_id', $this->cliente->id)->firstOrFail();
        $entrevista->documentos()->where('documento_codigo', 'w2')->firstOrFail()->update(['estado' => EstadoDocumento::NoSePudoLeer]);

        $documentos = $this->tool('consultar_entrevista')['documentos'];
        $w2 = collect($documentos['pendientes'])->firstWhere('nombre_oficial', 'Form W-2');

        $this->assertNotNull($w2);
        $this->assertTrue($w2['no_se_pudo_leer']);
        $this->assertStringContainsString('más nítido o en PDF', $w2['aviso_no_se_pudo_leer']);
        $this->assertSame(1, $documentos['no_se_pudo_leer']);
        $this->assertSame(0, $documentos['en_revision']);
        $this->assertFalse($documentos['todo_listo']);
        $this->assertNull(collect($documentos['pendientes'])->firstWhere('nombre_oficial', 'Form W-2')['comentario_del_preparador']);
    }

    public function test_consultar_entrevista_explica_los_documentos_que_faltan_sin_archivos(): void
    {
        Storage::fake(Documento::disco());
        $this->firmar();
        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'D1', 'valor' => 'si']]]);

        $documentos = $this->tool('consultar_entrevista')['documentos'];
        $w2 = collect($documentos['pendientes'])->firstWhere('nombre_oficial', 'Form W-2');

        $this->assertNotNull($w2);
        $this->assertNotEmpty($w2['quien_lo_envia']);
        $this->assertNotEmpty($w2['fecha_llegada']);
        $this->assertNotEmpty($w2['si_no_llega']);
        $this->assertNotEmpty($w2['por_que']);
        $this->assertFalse($w2['devuelto_por_el_preparador']);
        $this->assertFalse($documentos['todo_listo']);

        // El preparador lo devuelve: el agente sabe qué pidió, pero nunca ve el archivo.
        $entrevista = Entrevista::query()->where('user_id', $this->cliente->id)->firstOrFail();
        $item = $entrevista->documentos()->where('documento_codigo', 'w2')->firstOrFail();
        app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->create('w2-secreto.pdf', 10, 'application/pdf'));
        app(ChecklistDocumentos::class)->observar($item->fresh(), 'Falta la segunda página.', User::factory()->create(['role' => UserRole::Preparer]));

        $resultado = $this->tool('consultar_entrevista');
        $w2 = collect($resultado['documentos']['pendientes'])->firstWhere('nombre_oficial', 'Form W-2');

        $this->assertTrue($w2['devuelto_por_el_preparador']);
        $this->assertSame('Falta la segunda página.', $w2['comentario_del_preparador']);
        $this->assertSame(1, $resultado['documentos']['devueltos_por_el_preparador']);
        $this->assertStringNotContainsString('w2-secreto', json_encode($resultado));
        $this->assertStringNotContainsString('archivos', json_encode($resultado['documentos']));
    }

    public function test_con_todo_validado_o_en_no_aplica_no_falta_ningun_documento(): void
    {
        Storage::fake(Documento::disco());
        $this->firmar();
        $this->tool('guardar_respuestas', ['respuestas' => [['codigo' => 'D1', 'valor' => 'si']]]);
        $this->tool('consultar_entrevista');

        $checklist = app(ChecklistDocumentos::class);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $entrevista = Entrevista::query()->where('user_id', $this->cliente->id)->firstOrFail();

        foreach ($checklist->vigentes($entrevista) as $item) {
            if ($item->documento_codigo === 'w2') {
                app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
                $checklist->validar($item->fresh(), $preparador);
            } else {
                $checklist->marcarNoAplica($item, 'No me aplica.');
            }
        }

        $resultado = $this->tool('consultar_entrevista');

        $this->assertTrue($resultado['documentos']['todo_listo']);
        $this->assertSame(0, $resultado['documentos']['faltan']);
        $this->assertSame([], $resultado['documentos']['pendientes']);
        $this->assertSame(0, $resultado['falta_en_el_portal']['documentos_pendientes']);
    }

    public function test_con_la_entrevista_por_whatsapp_apagada_nunca_se_ofrece_guardar_respuestas(): void
    {
        $real = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15550005555']);
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
            ->handle(app(AgenteConversacionalService::class), $canal);
    }

    public function test_un_archivo_por_whatsapp_no_se_descarga_ni_se_guarda(): void
    {
        $this->firmar();
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

    private function conversacionDelCliente(int $horasAtras = 1): void
    {
        WhatsappControl::query()->create(['telefono' => self::TELEFONO, 'cliente_id' => $this->cliente->id, 'estado' => 'agente']);
        $mensaje = WhatsappMensaje::query()->create([
            'telefono' => self::TELEFONO, 'cliente_id' => $this->cliente->id, 'rol' => 'cliente',
            'contenido' => 'ya voy a firmar', 'mensaje_externo_id' => 'SM'.uniqid(), 'proveedor' => 'twilio',
        ]);
        $mensaje->forceFill(['created_at' => now()->subHours($horasAtras)])->save();
    }

    private function continuarTrasFirmar(): void
    {
        $canal = Mockery::mock(WhatsappChannel::class);
        $canal->shouldReceive('enviarTexto')->andReturn('SM'.uniqid());
        $this->app->instance(WhatsappChannel::class, $canal);

        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Listo, empecemos.']]]])]);

        (new ContinuarPorWhatsappTrasConsentimientoJob($this->cliente->id))->handle(app(AgenteConversacionalService::class), $canal);
    }

    public function test_al_firmar_el_agente_sigue_la_entrevista_por_whatsapp_sin_que_el_cliente_avise(): void
    {
        $this->conversacionDelCliente();
        $this->firmar();

        $this->continuarTrasFirmar();

        $mensajes = WhatsappMensaje::query()->orderBy('id')->get();
        $this->assertSame(ContinuarPorWhatsappTrasConsentimientoJob::AVISO_FIRMA, $mensajes[1]->contenido);
        $this->assertSame('agente', $mensajes[2]->rol->value);
        $this->assertSame('Listo, empecemos.', $mensajes[2]->contenido);
    }

    public function test_al_firmar_no_se_escribe_si_pasaron_mas_de_24_horas_ni_si_no_hay_conversacion(): void
    {
        $this->firmar();
        $this->continuarTrasFirmar();
        $this->assertSame(0, WhatsappMensaje::query()->count(), 'Sin conversación de WhatsApp no se escribe.');

        $this->conversacionDelCliente(horasAtras: 30);
        $this->continuarTrasFirmar();
        $this->assertSame(1, WhatsappMensaje::query()->count(), 'Fuera de la ventana de 24 horas Twilio rechaza el texto libre.');
    }
}
