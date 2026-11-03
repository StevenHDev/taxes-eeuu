<?php

namespace Tests\Feature\Lectura;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\UserRole;
use App\Events\ArchivoDocumentoSubido;
use App\Jobs\LeerDocumentoJob;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoLectura;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\EsquemaLectura;
use App\Services\Lectura\LectorDocumentos;
use App\Services\Lectura\Reglas\MotorReglas;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * F4.4: cada archivo subido se lee solo, en la cola, y el ítem queda
 * `leido` o `no_se_pudo_leer`; el comando relee lo que falta.
 */
class LeerAlSubirTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.openai.com/v1/chat/completions';

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        config([
            'tax.lectura_automatica' => true,
            'services.openai.api_key' => 'clave-de-prueba',
            'services.openai.vision_model' => 'modelo-de-vision',
            'services.openai.retry_backoff_ms' => 0,
        ]);

        $this->cliente = User::factory()->create(['role' => UserRole::Client]);
        $guardador = app(RespuestaGuardador::class);
        $this->entrevista = $guardador->entrevistaDe($this->cliente, CanalRespuesta::Portal);
        $guardador->guardar($this->entrevista, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $this->cliente);
    }

    private function item(): EntrevistaDocumento
    {
        return $this->entrevista->documentos()->where('documento_codigo', 'w2')->firstOrFail();
    }

    private function subir(EntrevistaDocumento $item): Documento
    {
        $imagen = imagecreatetruecolor(300, 200);
        ob_start();
        imagepng($imagen);

        return app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->createWithContent('doc.png', (string) ob_get_clean()));
    }

    private function fingirOpenAi(): void
    {
        $lectura = ['tipo_documento' => 'w2', 'anio' => 2025, 'paginas_legibles' => true, 'casillas' => [
            '1' => ['valor' => '45120.00', 'legibilidad' => 'claro', 'pagina' => 1, 'texto_cercano' => 'Wages'],
        ]];

        Http::fake([self::URL => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($lectura)]]]])]);
    }

    private function correr(int $documentoId): void
    {
        (new LeerDocumentoJob($documentoId))->handle(app(LectorDocumentos::class), app(DatosDocumento::class), app(ChecklistDocumentos::class), app(EsquemaLectura::class), app(MotorReglas::class));
    }

    public function test_subir_un_archivo_dispara_el_evento(): void
    {
        Queue::fake();
        Event::fake([ArchivoDocumentoSubido::class]);

        $archivo = $this->subir($this->item());

        Event::assertDispatched(ArchivoDocumentoSubido::class, fn ($e) => $e->documento->is($archivo));
    }

    public function test_el_evento_encola_el_job_con_el_archivo(): void
    {
        Queue::fake();

        $archivo = $this->subir($this->item());

        Queue::assertPushed(LeerDocumentoJob::class, 1);
        Queue::assertPushed(LeerDocumentoJob::class, fn ($job) => $job->documentoId === $archivo->id);
    }

    public function test_el_job_bien_deja_el_item_leido_y_los_datos_guardados(): void
    {
        Queue::fake();
        $item = $this->item();
        $archivo = $this->subir($item);
        $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado);

        $this->fingirOpenAi();
        $this->correr($archivo->id);

        $this->assertSame(EstadoDocumento::Leido, $item->fresh()->estado);

        $lectura = DocumentoLectura::query()->where('documento_id', $archivo->id)->sole();
        $this->assertSame(EstadoLectura::Leida, $lectura->estado);
        $this->assertSame('45120.00', $lectura->datos()->where('casilla', '1')->sole()->valor);
    }

    public function test_el_job_que_se_queda_sin_intentos_deja_el_item_no_se_pudo_leer(): void
    {
        Queue::fake();
        $item = $this->item();
        $archivo = $this->subir($item);
        Http::fake([self::URL => Http::response(['error' => ['code' => 'server_error', 'message' => 'SSN 123-45-6789']], 500)]);

        try {
            $this->correr($archivo->id);
            $this->fail('Debió lanzar una excepción para que la cola reintente.');
        } catch (RuntimeException $e) {
            $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado, 'Mientras quedan intentos, sigue subido.');
            (new LeerDocumentoJob($archivo->id))->failed($e);
        }

        $this->assertSame(EstadoDocumento::NoSePudoLeer, $item->fresh()->estado);

        $lectura = DocumentoLectura::query()->where('documento_id', $archivo->id)->sole();
        $this->assertSame(EstadoLectura::Fallida, $lectura->estado);
        $this->assertStringNotContainsString('123-45-6789', (string) $lectura->error);
    }

    public function test_un_item_ya_validado_u_observado_no_cambia_de_estado_al_leer(): void
    {
        Queue::fake();

        foreach ([EstadoDocumento::Validado, EstadoDocumento::ConObservacion] as $estado) {
            $item = $this->item();
            $archivo = $this->subir($item);
            $item->update(['estado' => $estado]);

            $this->fingirOpenAi();
            $this->correr($archivo->id);

            $this->assertSame($estado, $item->fresh()->estado);
            $this->assertSame(EstadoLectura::Leida, DocumentoLectura::query()->where('documento_id', $archivo->id)->sole()->estado, 'Igual se leyó y se guardaron los datos.');
        }
    }

    public function test_una_falla_final_no_cambia_un_item_que_ya_estaba_validado(): void
    {
        Queue::fake();
        $item = $this->item();
        $archivo = $this->subir($item);
        $item->update(['estado' => EstadoDocumento::Validado]);

        (new LeerDocumentoJob($archivo->id))->failed(new RuntimeException('x'));

        $this->assertSame(EstadoDocumento::Validado, $item->fresh()->estado);
    }

    public function test_con_la_bandera_apagada_no_se_lee_ni_se_llama_a_openai_y_el_item_sigue_subido(): void
    {
        Queue::fake();
        Http::fake();
        $item = $this->item();
        $archivo = $this->subir($item);
        config(['tax.lectura_automatica' => false]);

        $this->correr($archivo->id);

        Http::assertNothingSent();
        $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado);
        $this->assertSame(0, DocumentoLectura::query()->count());
    }

    public function test_un_archivo_retirado_o_inexistente_no_se_lee(): void
    {
        Queue::fake();
        Http::fake();
        $item = $this->item();
        $archivo = $this->subir($item);
        $item->update(['retirado_en' => now()]);

        $this->correr($archivo->id);
        $this->correr(999999);

        Http::assertNothingSent();
        $this->assertSame(0, DocumentoLectura::query()->count());
    }

    public function test_el_comando_con_dry_run_cuenta_sin_encolar(): void
    {
        Queue::fake();
        $this->subir($this->item());
        Queue::fake();

        $this->artisan('documentos:leer', ['--dry-run' => true])
            ->expectsOutput('Se leerían 1 archivos.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_el_comando_encola_lo_que_falta_y_salta_lo_ya_leido(): void
    {
        Queue::fake();
        $leido = $this->subir($this->item());
        $this->fingirOpenAi();
        $this->correr($leido->id);

        $pendiente = $this->subir($this->item());
        Queue::fake();

        $this->artisan('documentos:leer')->expectsOutput('Encolados 1 archivos.')->assertSuccessful();

        Queue::assertPushed(LeerDocumentoJob::class, 1);
        Queue::assertPushed(LeerDocumentoJob::class, fn ($job) => $job->documentoId === $pendiente->id);
    }

    public function test_el_comando_respeta_la_entrevista_solo_prueba_y_la_bandera(): void
    {
        Queue::fake();
        $this->subir($this->item());
        Queue::fake();

        $this->artisan('documentos:leer', ['--entrevista' => $this->entrevista->id + 1000])->expectsOutput('Encolados 0 archivos.');
        $this->artisan('documentos:leer', ['--solo-prueba' => true])->expectsOutput('Encolados 0 archivos.');

        $this->cliente->forceFill(['es_prueba' => true])->save();
        $this->artisan('documentos:leer', ['--solo-prueba' => true, '--entrevista' => $this->entrevista->id])->expectsOutput('Encolados 1 archivos.');

        Queue::fake();
        config(['tax.lectura_automatica' => false]);
        $this->artisan('documentos:leer')->assertSuccessful();
        Queue::assertNothingPushed();
    }
}
