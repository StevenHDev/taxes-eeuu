<?php

namespace Tests\Feature\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
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
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\VistaEntrevistaPreparador;
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

    public function test_un_archivo_que_no_se_pudo_leer_le_falta_al_cliente_y_uno_leido_no(): void
    {
        $this->responder('D1', 'si');
        $item = $this->item('w2');
        $this->subir($item)->assertSessionHasNoErrors();

        $item->update(['estado' => EstadoDocumento::Leido]);
        $this->assertTrue($this->fila($item->id)['resuelto']);

        $item->update(['estado' => EstadoDocumento::NoSePudoLeer]);
        $fila = $this->fila($item->id);
        $this->assertFalse($fila['resuelto']);
        $this->assertSame('no_se_pudo_leer', $fila['estado']);
    }

    public function test_las_props_del_portal_nunca_traen_lo_leido_de_los_documentos(): void
    {
        $this->responder('D1', 'si');
        $item = $this->item('w2');
        $archivo = app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->create('w2.pdf', 10, 'application/pdf'));
        $item->update(['estado' => EstadoDocumento::Leido]);

        $lectura = DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $item->id,
            'lector' => 'openai',
            'modelo' => 'modelo-de-prueba',
            'estado' => EstadoLectura::Leida,
            'paginas' => 1,
            'terminada_en' => now(),
        ]);
        $lectura->datos()->create([
            'entrevista_documento_id' => $item->id,
            'casilla' => 'a',
            'valor' => '987-65-4321',
            'valor_leido' => '987-65-4321',
            'confianza' => 0.9137,
            'motivo_confianza' => MotivoConfianza::EnTextoDelPdf,
            'legibilidad' => Legibilidad::Claro,
            'pagina' => 1,
            'recuadro' => ['x' => 0.1, 'y' => 0.2, 'ancho' => 0.3, 'alto' => 0.05],
            'evidencia' => 'Employee SSN 987-65-4321',
            'origen' => OrigenDato::Lector,
        ]);

        $props = [];
        $this->actingAs($this->cliente)->get(route('portal.entrevista.documentos'))->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        $json = json_encode($props);
        foreach (['documento_datos', 'valor_leido', 'confianza', 'motivo_confianza', 'legibilidad', 'recuadro', 'evidencia', '987-65-4321', '987654321', '0.9137'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $json, "Las props del portal no deben traer: {$prohibido}");
        }
        $this->assertSame('leido', $this->fila($item->id)['estado']);
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

    public function test_que_subir_y_las_plantillas_solo_salen_en_los_registros_propios(): void
    {
        $this->withUnencryptedCookie('language', 'es');
        $this->responder('D1', 'si');
        $this->responder('D12', 'si');
        $this->responder('D15', 'si');
        $w2 = $this->item('w2');
        $negocio = $this->item('registros-ingresos-gastos');
        $alquiler = $this->item('registros-alquiler');

        $fila = $this->fila($w2->id);
        $this->assertNull($fila['que_subir']);
        $this->assertNull($fila['plantillas']);
        $this->assertNull($fila['nota_cliente']);

        $fila = $this->fila($negocio->id);
        $this->assertStringContainsString('No tienes que sumar nada', $fila['que_subir']);
        $this->assertSame(asset('plantillas/registros-negocio-es.xlsx'), $fila['plantillas']['xlsx']);
        $this->assertSame(asset('plantillas/registros-negocio-es.pdf'), $fila['plantillas']['pdf']);

        $fila = $this->fila($alquiler->id);
        $this->assertSame(asset('plantillas/registros-alquiler-es.pdf'), $fila['plantillas']['pdf']);
    }

    public function test_en_ingles_el_texto_y_las_plantillas_salen_en_ingles(): void
    {
        $this->responder('D12', 'si');
        $this->item('registros-alquiler');
        $entrevista = $this->entrevista();
        $filas = app(DocumentosEntrevista::class)->lista($entrevista, app(PasosEntrevista::class)->situacion($entrevista), true);
        $fila = collect($filas)->firstWhere('codigo', 'registros-alquiler');

        $this->assertStringContainsString('the lease for each property', (string) $fila['que_subir']);
        $this->assertSame(asset('plantillas/registros-alquiler-en.xlsx'), $fila['plantillas']['xlsx']);
    }

    public function test_los_archivos_de_las_plantillas_existen(): void
    {
        foreach (['registros-negocio', 'registros-alquiler'] as $base) {
            foreach (['es', 'en'] as $idioma) {
                foreach (['xlsx', 'pdf'] as $formato) {
                    $this->assertFileExists(public_path("plantillas/{$base}-{$idioma}.{$formato}"));
                }
            }
        }
    }

    public function test_se_puede_subir_un_registro_propio_sin_usar_la_plantilla(): void
    {
        $this->responder('D12', 'si');
        $item = $this->item('registros-alquiler');

        $this->subir($item, 'contrato.pdf')->assertRedirect();

        $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado);
        $this->assertSame(1, Documento::query()->count());
    }

    public function test_el_cliente_guarda_y_borra_su_nota_sin_cambiar_el_estado(): void
    {
        $this->responder('D12', 'si');
        $item = $this->item('registros-alquiler');
        $ruta = route('portal.entrevista.documentos.nota', ['item' => $item->id]);

        $this->actingAs($this->cliente)->patch($ruta, ['nota' => '  La casa de Oak St. estuvo vacía en marzo.  '])->assertRedirect();

        $this->assertSame('La casa de Oak St. estuvo vacía en marzo.', $item->fresh()->nota_cliente);
        $this->assertSame(EstadoDocumento::Pendiente, $item->fresh()->estado);
        $this->assertSame('La casa de Oak St. estuvo vacía en marzo.', $this->fila($item->id)['nota_cliente']);

        $this->subir($item);
        $this->actingAs($this->cliente)->patch($ruta, ['nota' => 'Otra nota'])->assertRedirect();
        $this->assertSame(EstadoDocumento::Subido, $item->fresh()->estado);

        $this->actingAs($this->cliente)->patch($ruta, ['nota' => ''])->assertRedirect();
        $this->assertNull($item->fresh()->nota_cliente);
    }

    public function test_la_nota_no_pasa_de_mil_caracteres(): void
    {
        $this->responder('D12', 'si');
        $item = $this->item('registros-alquiler');

        $this->actingAs($this->cliente)
            ->patch(route('portal.entrevista.documentos.nota', ['item' => $item->id]), ['nota' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('nota');

        $this->assertNull($item->fresh()->nota_cliente);
    }

    public function test_un_cliente_no_escribe_la_nota_de_otro(): void
    {
        $this->responder('D12', 'si');
        $item = $this->item('registros-alquiler');
        $otro = $this->clienteConEntrevista();

        $this->actingAs($otro)
            ->patch(route('portal.entrevista.documentos.nota', ['item' => $item->id]), ['nota' => 'x'])
            ->assertNotFound();

        $this->assertNull($item->fresh()->nota_cliente);
    }

    public function test_el_preparador_ve_la_nota_del_cliente(): void
    {
        $this->responder('D15', 'si');
        $item = $this->item('registros-ingresos-gastos');
        $item->update(['nota_cliente' => 'Cobré 800 en efectivo sin recibo.']);

        $entrevista = $this->entrevista();
        $vista = app(VistaEntrevistaPreparador::class)->para($this->cliente, $entrevista->tax_year, false);
        $fila = collect($vista['documentos'])->firstWhere('id', $item->id);

        $this->assertSame('Cobré 800 en efectivo sin recibo.', $fila['nota_cliente']);
    }
}
