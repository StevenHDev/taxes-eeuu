<?php

namespace Tests\Feature\Lectura;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoLectura;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\LectorDocumentos;
use App\Services\Lectura\Reglas\MotorReglas;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lo común de las pruebas de F6 6.3b: un cliente con los registros del negocio
 * activos, archivos subidos desde `tests/Fixtures/lectura/montos/` y lectura
 * con el lector que de verdad se usa (`LectorDocumentos`, con la lectura
 * automática encendida y la clave de OpenAI puesta: si algo la llamara, Http
 * lo registraría). OpenAI nunca se llama.
 */
abstract class RegistrosTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $cliente;

    protected Entrevista $entrevista;

    /** @var list<string> */
    protected array $registrosDeLog = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake();
        Storage::fake(Documento::disco());
        config([
            'tax.lectura_automatica' => true,
            'services.openai.api_key' => 'clave-de-prueba',
            'services.openai.vision_model' => 'modelo-de-vision',
            'inertia.testing.ensure_pages_exist' => false,
        ]);
        $this->seed(CatalogoFiscalSeeder::class);
        app(ConsentimientosCargador::class)->cargar();

        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        app(Consentimientos::class)->firmar($this->cliente, (int) config('tax.current_tax_year'), 1, 'Cliente', null, null);
        $this->entrevista = app(RespuestaGuardador::class)->entrevistaDe($this->cliente, CanalRespuesta::Portal);

        // Estas respuestas activan los registros del negocio (como en LecturaMontosTest).
        foreach ([['B1', 'casado'], ['E6', 'si'], ['E8', 'si'], ['D15', 'si'], ['G1', 'si']] as [$pregunta, $respuesta]) {
            app(RespuestaGuardador::class)->guardar($this->entrevista, new RespuestaData($pregunta, $respuesta), CanalRespuesta::Portal, $this->cliente);
        }

        Event::listen(MessageLogged::class, function (MessageLogged $log) {
            $this->registrosDeLog[] = $log->message.json_encode($log->context);
        });
    }

    protected function itemRegistros(): EntrevistaDocumento
    {
        return app(ChecklistDocumentos::class)->primero($this->entrevista, 'registros-ingresos-gastos', null) ?? $this->fail('Los registros del negocio no están activos.');
    }

    protected function fixture(string $nombre): string
    {
        return base_path("tests/Fixtures/lectura/montos/{$nombre}");
    }

    protected function subir(string $nombre, ?string $contenido = null, ?string $como = null): Documento
    {
        return app(DocumentosEntrevista::class)->subir(
            $this->itemRegistros(),
            UploadedFile::fake()->createWithContent($como ?? $nombre, $contenido ?? (string) file_get_contents($this->fixture($nombre))),
        );
    }

    /**
     * Lee un archivo con el lector que usa la plataforma, guarda la lectura y corre las reglas.
     */
    protected function leer(Documento $archivo): DocumentoLectura
    {
        $lectura = DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $archivo->entrevista_documento_id,
            'lector' => 'openai',
            'estado' => EstadoLectura::EnCola,
        ]);

        app(DatosDocumento::class)->guardarLectura($lectura, app(LectorDocumentos::class)->leer($archivo, $lectura, 'registros-ingresos-gastos', $this->entrevista->tax_year));
        app(MotorReglas::class)->correr($this->entrevista);

        return $lectura->fresh();
    }

    protected function subirYLeer(string $nombre): DocumentoLectura
    {
        $lectura = $this->leer($this->subir($nombre));

        Http::assertNothingSent();

        return $lectura;
    }

    /**
     * Los renglones de una lectura por la línea del Schedule C: línea → [monto, ...].
     *
     * @return array<string, list<string>>
     */
    protected function montosPorLinea(DocumentoLectura $lectura): array
    {
        return $lectura->renglones->groupBy(fn ($r) => $r->categoria ?? 'sin')->map(fn ($g) => $g->pluck('monto')->all())->all();
    }
}
