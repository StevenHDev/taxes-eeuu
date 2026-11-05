<?php

namespace Tests\Feature\Entrevista;

use App\Enums\AccionAuditoria;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * F3.4: el preparador revisa los documentos del checklist de sus clientes
 * desde la ficha: valida, devuelve con observación o rechaza un "no
 * aplica". Lo ve en la ficha y en la lista de clientes.
 */
class RevisionDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private User $preparador;

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        $this->preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $this->preparador->id]);

        $guardador = app(RespuestaGuardador::class);
        $this->entrevista = $guardador->entrevistaDe($this->cliente, CanalRespuesta::Portal);
        $guardador->guardar($this->entrevista, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $this->cliente);
    }

    private function item(string $codigo): EntrevistaDocumento
    {
        return $this->entrevista->documentos()->where('documento_codigo', $codigo)->firstOrFail();
    }

    private function subir(string $codigo): void
    {
        app(DocumentosEntrevista::class)->subir($this->item($codigo), UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
    }

    private function revisar(EntrevistaDocumento $item, array $datos, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->preparador)
            ->from(route('clientes.show', $this->cliente))
            ->patch(route('clientes.entrevista.documentos.update', [$this->cliente, $item]), $datos);
    }

    public function test_validar_observar_y_rechazar_quedan_en_el_estado_y_en_la_bitacora(): void
    {
        $this->subir('w2');

        $this->revisar($this->item('w2'), ['accion' => 'observar', 'texto' => 'Falta la segunda página.'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoDocumento::ConObservacion, $this->item('w2')->estado);
        $this->assertSame('Falta la segunda página.', $this->item('w2')->observacion);

        $this->revisar($this->item('w2'), ['accion' => 'validar'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoDocumento::Validado, $this->item('w2')->estado);
        $this->assertSame($this->preparador->id, $this->item('w2')->revisado_por);

        app(ChecklistDocumentos::class)->marcarNoAplica($this->item('id-foto'), 'La entregué en la oficina.');
        $this->revisar($this->item('id-foto'), ['accion' => 'rechazar_no_aplica', 'texto' => 'La necesitamos para tu identidad.'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoDocumento::NoAplicaRechazado, $this->item('id-foto')->estado);

        $this->assertTrue(BitacoraActividad::query()
            ->where('auditable_type', EntrevistaDocumento::class)
            ->where('auditable_id', $this->item('w2')->id)
            ->where('accion', AccionAuditoria::Actualizado->value)
            ->exists());
    }

    public function test_un_cambio_no_permitido_o_sin_texto_vuelve_como_error(): void
    {
        $this->revisar($this->item('w2'), ['accion' => 'validar'])->assertSessionHasErrors('accion');
        $this->revisar($this->item('w2'), ['accion' => 'observar'])->assertSessionHasErrors('texto');
        $this->revisar($this->item('w2'), ['accion' => 'borrar'])->assertSessionHasErrors('accion');

        $this->assertSame(EstadoDocumento::Pendiente, $this->item('w2')->estado);
    }

    public function test_otro_preparador_y_el_cliente_no_revisan(): void
    {
        $this->subir('w2');
        $otro = User::factory()->create(['role' => UserRole::Preparer]);

        $this->revisar($this->item('w2'), ['accion' => 'validar'], $otro)->assertForbidden();
        $this->revisar($this->item('w2'), ['accion' => 'validar'], $this->cliente)->assertForbidden();

        $otroCliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $this->preparador->id]);
        $this->actingAs($this->preparador)
            ->patch(route('clientes.entrevista.documentos.update', [$otroCliente, $this->item('w2')]), ['accion' => 'validar'])
            ->assertForbidden();

        $this->assertSame(EstadoDocumento::Subido, $this->item('w2')->estado);
    }

    public function test_la_ficha_trae_el_estado_y_el_resumen(): void
    {
        $this->subir('w2');
        app(ChecklistDocumentos::class)->marcarNoAplica($this->item('id-foto'), 'La entregué en la oficina.');

        $this->actingAs($this->preparador)
            ->get(route('clientes.ficha.documentos', $this->cliente))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where('entrevista.documentos_resumen.por_revisar', 1)
                    ->where('entrevista.documentos_resumen.no_aplica', 1)
                    ->where('entrevista.documentos', fn ($documentos) => collect($documentos)->contains(
                        fn ($d) => $d['codigo'] === 'id-foto' && $d['estado'] === 'no_aplica' && $d['motivo_no_aplica'] === 'La entregué en la oficina.',
                    ))));
    }

    public function test_la_lista_de_clientes_dice_cuanto_falta(): void
    {
        $this->subir('w2');

        $this->actingAs($this->preparador)
            ->get(route('clientes.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clientes.0.documentos.faltan', 1)
                ->where('clientes.0.documentos.por_revisar', 1)
                ->where('clientes.0.documentos.completo', false));
    }
}
