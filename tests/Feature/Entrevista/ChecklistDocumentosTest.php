<?php

namespace Tests\Feature\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\TransicionNoPermitida;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F3.2: el checklist de documentos guardado. Un ítem por documento, por
 * persona y por fuente; lo que deja de aplicar se retira sin borrar
 * archivos; los cambios de estado pasan solo por ChecklistDocumentos.
 */
class ChecklistDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    private Entrevista $entrevista;

    private ChecklistDocumentos $checklist;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        $this->cliente = User::factory()->create(['role' => UserRole::Client]);
        $this->entrevista = app(RespuestaGuardador::class)->entrevistaDe($this->cliente, CanalRespuesta::Portal);
        $this->checklist = app(ChecklistDocumentos::class);
    }

    private function responder(string $codigo, mixed $valor, ?int $personaId = null): void
    {
        app(RespuestaGuardador::class)->guardar($this->entrevista, new RespuestaData($codigo, $valor, $personaId), CanalRespuesta::Portal, $this->cliente);
    }

    /**
     * @return list<string> "codigo#numero" de los vigentes
     */
    private function vigentes(?string $codigo = null): array
    {
        return $this->checklist->vigentes($this->entrevista)
            ->when($codigo !== null, fn ($c) => $c->where('documento_codigo', $codigo))
            ->map(fn (EntrevistaDocumento $i) => "{$i->documento_codigo}#{$i->numero}")
            ->values()
            ->all();
    }

    private function item(string $codigo, int $numero = 1, ?int $personaId = null): EntrevistaDocumento
    {
        return $this->entrevista->documentos()->where('documento_codigo', $codigo)->where('persona_id', $personaId)->where('numero', $numero)->firstOrFail();
    }

    private function subir(EntrevistaDocumento $item): void
    {
        app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->create('w2.pdf', 10, 'application/pdf'));
    }

    public function test_un_w2_por_cada_empleo_y_bajar_la_cantidad_retira_sin_borrar_archivos(): void
    {
        $this->responder('D1', 'si');
        $this->responder('D1.1', 2);
        $this->assertSame(['w2#1', 'w2#2'], $this->vigentes('w2'));

        $this->subir($this->item('w2', 2));
        $this->responder('D1.1', 1);

        $this->assertSame(['w2#1'], $this->vigentes('w2'));
        $segundo = $this->item('w2', 2);
        $this->assertNotNull($segundo->retirado_en);
        $this->assertSame(1, $segundo->archivos()->count(), 'Retirar no borra los archivos.');

        $this->responder('D1.1', 2);
        $this->assertSame(['w2#1', 'w2#2'], $this->vigentes('w2'), 'Vuelve si se activa de nuevo.');
        $this->assertSame(EstadoDocumento::Subido, $this->item('w2', 2)->estado);
    }

    public function test_d1_no_retira_el_w2(): void
    {
        $this->responder('D1', 'si');
        $this->responder('D1', 'no');

        $this->assertSame([], $this->vigentes('w2'));
        $this->assertNotNull($this->item('w2')->retirado_en);
    }

    public function test_la_tarjeta_de_cada_persona_es_un_item_aparte(): void
    {
        $this->responder('C0', 'si');
        $una = app(RespuestaGuardador::class)->agregarPersona($this->entrevista);
        $otra = app(RespuestaGuardador::class)->agregarPersona($this->entrevista);
        foreach ([$una, $otra] as $persona) {
            $this->responder('C3', 'hijo', $persona->id);
            $this->responder('C4', 12, $persona->id);
        }
        $this->responder('C9', 'ssn_valido', $una->id);
        $this->responder('C9', 'itin', $otra->id);

        $this->assertTrue($this->entrevista->documentos()->vigentes()->where('documento_codigo', 'tarjeta-ssn')->where('persona_id', $una->id)->exists());
        $this->assertTrue($this->entrevista->documentos()->vigentes()->where('documento_codigo', 'tarjeta-ssn')->where('persona_id', $otra->id)->exists());

        app(RespuestaGuardador::class)->quitarPersona($otra);
        $this->assertNotNull($this->item('tarjeta-ssn', 1, $otra->id)->retirado_en, 'Quitar la persona retira su tarjeta.');
    }

    public function test_lo_que_agrega_el_cliente_queda_mientras_el_documento_siga_activado(): void
    {
        $this->responder('D9', 'si');
        $this->checklist->agregar($this->entrevista, '1099-int', null);
        $otro = $this->checklist->agregar($this->entrevista, EntrevistaDocumento::OTRO, null, 'Carta del banco');

        $this->checklist->sincronizar($this->entrevista);
        $this->assertSame(['1099-int#1', '1099-int#2'], $this->vigentes('1099-int'));

        $this->responder('D9', 'no');
        $this->assertSame([], $this->vigentes('1099-int'));
        $this->assertNull($otro->fresh()->retirado_en, '"Otro documento" nunca se retira.');
    }

    public function test_agregar_exige_un_documento_de_la_lista_o_una_descripcion(): void
    {
        $this->expectException(TransicionNoPermitida::class);
        $this->checklist->agregar($this->entrevista, 'w2', null);
    }

    public function test_transiciones_permitidas(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $this->responder('D1', 'si');
        $w2 = $this->item('w2');

        $this->subir($w2);
        $this->assertSame(EstadoDocumento::Subido, $w2->fresh()->estado);

        $this->checklist->observar($w2->fresh(), 'El archivo está cortado.', $preparador);
        $this->assertSame(EstadoDocumento::ConObservacion, $w2->fresh()->estado);
        $this->assertSame('El archivo está cortado.', $w2->fresh()->observacion);

        $this->subir($w2->fresh());
        $this->checklist->validar($w2->fresh(), $preparador);
        $this->assertSame(EstadoDocumento::Validado, $w2->fresh()->estado);
        $this->assertSame($preparador->id, $w2->fresh()->revisado_por);
        $this->assertNull($w2->fresh()->observacion);

        $this->checklist->marcarNoAplica($this->item('id-foto'), 'Ya la entregué en la oficina.');
        $this->assertSame(EstadoDocumento::NoAplica, $this->item('id-foto')->estado);
        $this->assertTrue($this->checklist->completo($this->entrevista), 'Validado y "no aplica" cierran el checklist.');

        $this->checklist->rechazarNoAplica($this->item('id-foto'), 'La necesitamos para verificar tu identidad.', $preparador);
        $this->assertSame(EstadoDocumento::NoAplicaRechazado, $this->item('id-foto')->estado);
        $this->assertFalse($this->checklist->completo($this->entrevista));
    }

    public function test_transiciones_no_permitidas(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $this->responder('D1', 'si');
        $w2 = $this->item('w2');

        foreach ([
            fn () => $this->checklist->validar($w2, $preparador),
            fn () => $this->checklist->marcarNoAplica($w2, '   '),
            fn () => $this->checklist->rechazarNoAplica($w2, 'No', $preparador),
        ] as $intento) {
            try {
                $intento();
                $this->fail('Debió rechazarse.');
            } catch (TransicionNoPermitida) {
                $this->assertSame(EstadoDocumento::Pendiente, $w2->fresh()->estado);
            }
        }

        $this->subir($w2);
        $this->expectException(TransicionNoPermitida::class);
        $this->checklist->observar($w2->fresh(), '', $preparador);
    }

    public function test_el_comando_liga_los_archivos_de_la_lista_de_2_6(): void
    {
        $this->responder('D1', 'si');
        $suelto = Documento::query()->create([
            'user_id' => $this->cliente->id, 'tax_year' => 2025, 'documento_codigo' => 'w2', 'entrevista_id' => $this->entrevista->id,
            'file_path' => 'documentos/x.pdf', 'file_original_name' => 'w2.pdf', 'file_mime_type' => 'application/pdf',
            'file_size' => 10, 'formato' => 'pdf', 'estado_validacion' => 'recibido',
        ]);

        $this->artisan('documentos:sincronizar')->assertSuccessful();
        $this->artisan('documentos:sincronizar')->expectsOutputToContain('ligados a su documento: 0')->assertSuccessful();

        $this->assertSame($this->item('w2')->id, $suelto->fresh()->entrevista_documento_id);
        $this->assertSame(EstadoDocumento::Subido, $this->item('w2')->estado);
    }
}
