<?php

namespace Tests\Feature\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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

    private function item(string $codigo, ?int $personaId = null, ?User $cliente = null, int $numero = 1): EntrevistaDocumento
    {
        $entrevista = $this->entrevista($cliente);
        app(ChecklistDocumentos::class)->primero($entrevista, $codigo, $personaId);

        return $entrevista->documentos()->where('documento_codigo', $codigo)->where('persona_id', $personaId)->where('numero', $numero)->firstOrFail();
    }

    private function subir(EntrevistaDocumento $item, string $nombre = 'doc.pdf', ?User $cliente = null): TestResponse
    {
        return $this->actingAs($cliente ?? $this->cliente)
            ->post(route('portal.entrevista.documentos.archivos.store', ['item' => $item->id]), [
                'archivos' => [UploadedFile::fake()->createWithContent($nombre, 'contenido de la carta ITIN')],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(int $itemId): array
    {
        $fila = [];
        $this->actingAs($this->cliente)->get(route('portal.entrevista.documentos'))->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$fila, $itemId) {
                $fila = collect($page->toArray()['props']['documentos'])->firstWhere('id', $itemId);
            });

        return $fila;
    }

    public function test_cada_fila_trae_su_informacion_y_su_estado(): void
    {
        $this->responder('D1', 'si');
        $item = $this->item('w2');

        $fila = $this->fila($item->id);

        $this->assertSame('w2', $fila['codigo']);
        $this->assertSame('Form W-2', $fila['nombre_oficial']);
        $this->assertNotEmpty($fila['emisor']);
        $this->assertSame('2026-02-02', $fila['fecha_llegada']);
        $this->assertNotEmpty($fila['si_no_llega']);
        $this->assertNotEmpty($fila['nombre']);
        $this->assertNotEmpty($fila['por_que']);
        $this->assertSame(['pdf', 'jpg', 'png', 'heic'], $fila['formatos']);
        $this->assertSame('pendiente', $fila['estado']);
        $this->assertFalse($fila['resuelto']);
        $this->assertNull($fila['numero']);
        $this->assertTrue($fila['puede_agregar_otro']);
    }

    public function test_subir_guarda_cifrado_y_pasa_el_item_a_subido(): void
    {
        $this->responder('C0', 'si');
        $persona = app(RespuestaGuardador::class)->agregarPersona($this->entrevista());
        $this->responder('C9', 'itin', $persona->id);
        $item = $this->item('tarjeta-ssn', $persona->id);

        $this->subir($item, 'itin.pdf')->assertSessionHasNoErrors()->assertRedirect();

        $documento = Documento::query()->sole();
        $this->assertSame('tarjeta-ssn', $documento->documento_codigo);
        $this->assertSame($persona->id, $documento->persona_id);
        $this->assertSame($item->id, $documento->entrevista_documento_id);
        $this->assertNull($documento->forma);
        $this->assertTrue($documento->cifrado);
        $this->assertStringNotContainsString('carta ITIN', Storage::disk(Documento::disco())->get($documento->file_path));
        $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado);
        $this->assertTrue($this->fila($item->id)['resuelto']);
    }

    public function test_un_formato_no_permitido_o_un_item_inexistente_falla(): void
    {
        $item = $this->item('id-foto');

        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.archivos.store', ['item' => $item->id]), [
                'archivos' => [UploadedFile::fake()->create('foto.exe', 10)],
            ])
            ->assertSessionHasErrors('archivos.0');

        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.archivos.store', ['item' => 99999]), [
                'archivos' => [UploadedFile::fake()->create('w2.pdf', 10)],
            ])
            ->assertNotFound();

        $this->assertSame(0, Documento::query()->count());
        $this->assertSame(EstadoDocumento::Pendiente, $item->fresh()->estado);
    }

    public function test_no_aplica_pide_motivo_y_lo_guarda(): void
    {
        $this->responder('D1', 'si');
        $item = $this->item('w2');
        $url = route('portal.entrevista.documentos.no-aplica', ['item' => $item->id]);

        $this->actingAs($this->cliente)->post($url, ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->actingAs($this->cliente)->post($url, ['motivo' => str_repeat('a', 501)])->assertSessionHasErrors('motivo');
        $this->assertSame(EstadoDocumento::Pendiente, $item->fresh()->estado);

        $this->actingAs($this->cliente)->post($url, ['motivo' => 'No trabajé en ese empleo'])->assertSessionHasNoErrors();

        $this->assertSame(EstadoDocumento::NoAplica, $item->fresh()->estado);
        $this->assertSame('No trabajé en ese empleo', $item->fresh()->motivo_no_aplica);
        $this->assertTrue($this->fila($item->id)['resuelto']);

        // Un "no aplica" todavía deja subir: vuelve a "subido" y borra el motivo.
        $this->subir($item)->assertSessionHasNoErrors();
        $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado);
        $this->assertNull($item->fresh()->motivo_no_aplica);
    }

    public function test_no_aplica_en_un_estado_que_no_lo_permite_es_un_error_junto_al_campo(): void
    {
        $item = $this->item('id-foto');
        $this->subir($item);

        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.no-aplica', ['item' => $item->id]), ['motivo' => 'No lo tengo'])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado);
    }

    public function test_una_observacion_del_preparador_sube_la_fila_y_se_muestra(): void
    {
        $this->responder('D1', 'si');
        $w2 = $this->item('w2');
        $id = $this->item('id-foto');
        $this->subir($w2);
        app(ChecklistDocumentos::class)->observar($w2->fresh(), 'La foto está borrosa', User::factory()->create(['role' => UserRole::Preparer]));

        $filas = [];
        $this->actingAs($this->cliente)->get(route('portal.entrevista.documentos'))
            ->assertInertia(function (AssertableInertia $page) use (&$filas) {
                $filas = collect($page->toArray()['props']['documentos']);
            });

        $this->assertSame($w2->id, $filas->first()['id']);
        $this->assertSame('con_observacion', $filas->first()['estado']);
        $this->assertSame('La foto está borrosa', $filas->first()['observacion']);
        $this->assertSame($id->id, $filas->get(1)['id']);
    }

    public function test_otro_documento_crea_un_item_otro_con_sus_archivos(): void
    {
        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.otro.store'), ['descripcion' => '', 'archivos' => [UploadedFile::fake()->create('carta.pdf', 10)]])
            ->assertSessionHasErrors('descripcion');
        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.otro.store'), ['descripcion' => 'Carta del IRS'])
            ->assertSessionHasErrors('archivos');

        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.otro.store'), [
                'descripcion' => 'Carta del IRS',
                'archivos' => [UploadedFile::fake()->create('carta.pdf', 10)],
            ])
            ->assertSessionHasNoErrors();

        $item = $this->entrevista()->documentos()->where('documento_codigo', EntrevistaDocumento::OTRO)->sole();
        $this->assertSame('Carta del IRS', $item->descripcion);
        $this->assertSame(EstadoDocumento::Subido, $item->estado);
        $this->assertSame(1, $item->archivos()->count());

        $this->actingAs($this->cliente)->get(route('portal.entrevista.documentos'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('documentos.1.codigo', 'otro')
                ->where('documentos.1.nombre', 'Carta del IRS'));
    }

    public function test_agregar_otro_de_un_documento_por_fuente_crea_el_numero_siguiente(): void
    {
        $this->responder('D1', 'si');
        $this->responder('D1.1', 1);
        $w2 = $this->item('w2');
        $this->assertTrue($this->fila($w2->id)['puede_agregar_otro']);

        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.otro.agregar', ['item' => $w2->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 2], $this->entrevista()->documentos()->where('documento_codigo', 'w2')->orderBy('numero')->pluck('numero')->all());
        $this->assertSame(2, $this->item('w2', null, null, 2)->numero);
        $this->assertSame(1, $this->fila($w2->id)['numero']);
    }

    public function test_agregar_otro_en_un_documento_unico_falla(): void
    {
        $item = $this->item('id-foto');

        $this->actingAs($this->cliente)
            ->post(route('portal.entrevista.documentos.otro.agregar', ['item' => $item->id]))
            ->assertSessionHasErrors('agregar');

        $this->assertSame(1, $this->entrevista()->documentos()->where('documento_codigo', 'id-foto')->count());
    }

    public function test_dos_empleos_muestran_dos_w2(): void
    {
        $this->responder('D1', 'si');
        $this->responder('D1.1', 2);

        $this->actingAs($this->cliente)->get(route('portal.entrevista.documentos'))
            ->assertInertia(function (AssertableInertia $page) {
                $w2 = collect($page->toArray()['props']['documentos'])->where('codigo', 'w2')->values();
                $this->assertCount(2, $w2);
                $this->assertSame([1, 2], $w2->pluck('numero')->all());
            });
    }

    public function test_un_cliente_no_ve_ni_toca_items_de_otro(): void
    {
        $item = $this->item('id-foto');
        $this->subir($item, 'licencia.jpg');

        $otro = $this->clienteConEntrevista();

        $this->actingAs($otro)->get(route('portal.entrevista.documentos'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('documentos.0.archivos', [])->where('documentos.0.estado', 'pendiente'));

        $this->subir($item, 'otro.pdf', $otro)->assertNotFound();
        $this->actingAs($otro)->post(route('portal.entrevista.documentos.no-aplica', ['item' => $item->id]), ['motivo' => 'x'])->assertNotFound();
        $this->actingAs($otro)->post(route('portal.entrevista.documentos.otro.agregar', ['item' => $item->id]))->assertNotFound();

        $this->assertSame(1, Documento::query()->count());
        $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado);
    }

    public function test_un_preparador_no_usa_las_acciones_del_cliente(): void
    {
        $item = $this->item('id-foto');
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->subir($item, 'x.pdf', $preparador)->assertRedirect();
        $this->assertSame(0, Documento::query()->count());
    }

    public function test_la_pantalla_1_trae_la_identificacion_para_subir_ahi(): void
    {
        $this->actingAs($this->cliente)->get(route('portal.entrevista.paso', ['pantalla' => 1]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('documentos.0.codigo', 'id-foto')
                ->where('preguntas', fn ($preguntas) => ! collect($preguntas)->contains('codigo', 'A4')));
    }
}
