<?php

namespace Tests\Feature\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * F2.6: los documentos que pide la entrevista, su lista y su carga por el
 * portal.
 */
class DocumentosTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        app(ConsentimientosCargador::class)->cargar();
        $this->cliente = $this->clienteConEntrevista();
    }

    private function clienteConEntrevista(): User
    {
        $cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        $cliente->forceFill(['entrevista_catalogo' => true])->save();
        app(Consentimientos::class)->firmar($cliente, (int) config('tax.current_tax_year'), 1, 'Cliente', null, null);

        return $cliente;
    }

    private function entrevista(?User $cliente = null): Entrevista
    {
        return app(RespuestaGuardador::class)->entrevistaDe($cliente ?? $this->cliente, CanalRespuesta::Portal);
    }

    private function responder(string $codigo, mixed $valor, ?int $personaId = null): void
    {
        app(RespuestaGuardador::class)->guardar($this->entrevista(), new RespuestaData($codigo, $valor, $personaId), CanalRespuesta::Portal, $this->cliente);
    }

    /**
     * @return list<string> "codigo" o "codigo#persona"
     */
    private function lista(): array
    {
        $lista = [];
        $this->actingAs($this->cliente)->get(route('portal.entrevista.documentos'))->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$lista) {
                $lista = collect($page->toArray()['props']['documentos'])
                    ->map(fn (array $d) => $d['persona_id'] === null ? $d['codigo'] : "{$d['codigo']}#{$d['persona_id']}")
                    ->all();
            });

        return $lista;
    }

    public function test_la_identificacion_va_siempre_y_primero(): void
    {
        $this->assertSame(['id-foto'], $this->lista());
    }

    public function test_d1_si_pide_el_w2_y_d1_no_lo_saca(): void
    {
        $this->responder('D1', 'si');
        $this->assertContains('w2', $this->lista());

        $this->responder('D1', 'no');
        $this->assertNotContains('w2', $this->lista());
    }

    public function test_la_tarjeta_de_cada_persona_es_un_documento_aparte(): void
    {
        $this->responder('A3', 'ssn_valido');
        $this->responder('C0', 'si');
        $persona = app(RespuestaGuardador::class)->agregarPersona($this->entrevista());
        $this->responder('C9', 'ssn_valido', $persona->id);

        $lista = $this->lista();
        $this->assertContains('tarjeta-ssn', $lista);
        $this->assertContains("tarjeta-ssn#{$persona->id}", $lista);

        $this->actingAs($this->cliente)->get(route('portal.entrevista.persona', ['persona' => $persona->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('documentos', 1)
                ->where('documentos.0.codigo', 'tarjeta-ssn')
                ->where('documentos.0.persona_id', $persona->id));
    }

    public function test_subir_guarda_cifrado_con_el_codigo_y_la_persona(): void
    {
        $this->responder('C0', 'si');
        $persona = app(RespuestaGuardador::class)->agregarPersona($this->entrevista());
        $this->responder('C9', 'itin', $persona->id);

        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.store', ['codigo' => 'tarjeta-ssn']), [
                'persona_id' => $persona->id,
                'archivos' => [UploadedFile::fake()->createWithContent('itin.pdf', 'contenido de la carta ITIN')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $documento = Documento::query()->sole();
        $this->assertSame('tarjeta-ssn', $documento->documento_codigo);
        $this->assertSame($persona->id, $documento->persona_id);
        $this->assertNull($documento->forma);
        $this->assertTrue($documento->cifrado);
        $this->assertStringNotContainsString('carta ITIN', Storage::disk(Documento::disco())->get($documento->file_path));
    }

    public function test_un_formato_no_permitido_o_un_documento_que_no_se_pide_falla(): void
    {
        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.store', ['codigo' => 'id-foto']), [
                'archivos' => [UploadedFile::fake()->create('foto.exe', 10)],
            ])
            ->assertSessionHasErrors('archivos.0');

        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.store', ['codigo' => 'w2']), [
                'archivos' => [UploadedFile::fake()->create('w2.pdf', 10)],
            ])
            ->assertSessionHasErrors('archivos');

        $this->assertSame(0, Documento::query()->count());
    }

    public function test_un_cliente_no_ve_ni_sube_documentos_de_otro(): void
    {
        $this->responder('C0', 'si');
        $persona = app(RespuestaGuardador::class)->agregarPersona($this->entrevista());
        $this->responder('C9', 'itin', $persona->id);
        $this->actingAs($this->cliente)->post(route('portal.entrevista.documentos.store', ['codigo' => 'id-foto']), [
            'archivos' => [UploadedFile::fake()->create('licencia.jpg', 10)],
        ]);

        $otro = $this->clienteConEntrevista();

        $this->actingAs($otro)->get(route('portal.entrevista.documentos'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('documentos.0.archivos', []));

        $this->actingAs($otro)
            ->post(route('portal.entrevista.documentos.store', ['codigo' => 'tarjeta-ssn']), [
                'persona_id' => $persona->id,
                'archivos' => [UploadedFile::fake()->create('itin.pdf', 10)],
            ])
            ->assertSessionHasErrors('archivos');

        $this->assertSame(1, Documento::query()->count());
    }

    public function test_la_pantalla_1_trae_la_identificacion_para_subir_ahi(): void
    {
        $this->actingAs($this->cliente)->get(route('portal.entrevista.paso', ['pantalla' => 1]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('documentos.0.codigo', 'id-foto')
                ->where('preguntas', fn ($preguntas) => ! collect($preguntas)->contains('codigo', 'A4')));
    }
}
