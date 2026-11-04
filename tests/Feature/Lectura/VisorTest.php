<?php

namespace Tests\Feature\Lectura;

use App\Enums\AccionAuditoria;
use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\DocumentoDatoCambio;
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Lectura\EsquemaLectura;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F4.6a: el backend del visor. Permisos, props sin lo sensible en claro,
 * imágenes descifradas al vuelo, revelar con contraseña y corregir.
 */
class VisorTest extends TestCase
{
    use RefreshDatabase;

    private const SSN = '123-45-6789';

    private User $preparador;

    private User $cliente;

    private Entrevista $entrevista;

    private EntrevistaDocumento $item;

    private DocumentoLectura $lectura;

    private DocumentoDato $monto;

    private DocumentoDato $ssn;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::disco());
        Queue::fake();
        // La pantalla (clientes/visor) es la subtarea 4.6b: aún no existe el .tsx.
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->seed(CatalogoFiscalSeeder::class);

        $this->preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $this->preparador->id]);

        $guardador = app(RespuestaGuardador::class);
        $this->entrevista = $guardador->entrevistaDe($this->cliente, CanalRespuesta::Portal);
        $guardador->guardar($this->entrevista, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $this->cliente);

        $this->item = $this->entrevista->documentos()->where('documento_codigo', 'w2')->firstOrFail();
        $archivo = app(DocumentosEntrevista::class)->subir($this->item, UploadedFile::fake()->create('w2.pdf', 10, 'application/pdf'));
        $this->lectura = $this->lectura($archivo, $this->item);

        $casillas = collect(app(EsquemaLectura::class)->casillas('w2', $this->entrevista->tax_year))->keyBy('casilla');
        $sensible = $casillas->firstWhere('sensible', true)['casilla'];
        $normal = $casillas->firstWhere('sensible', false)['casilla'];
        $this->ssn = $this->dato($this->lectura, $sensible, self::SSN);
        $this->monto = $this->dato($this->lectura, $normal, '45120.00');
    }

    private function lectura(Documento $archivo, EntrevistaDocumento $item): DocumentoLectura
    {
        return DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $item->id,
            'lector' => 'openai',
            'modelo' => 'modelo-de-prueba',
            'estado' => EstadoLectura::Leida,
            'paginas' => 1,
            'terminada_en' => now(),
        ]);
    }

    private function dato(DocumentoLectura $lectura, string $casilla, string $valor): DocumentoDato
    {
        return $lectura->datos()->create([
            'entrevista_documento_id' => $lectura->entrevista_documento_id,
            'casilla' => $casilla,
            'valor' => $valor,
            'valor_leido' => $valor,
            'confianza' => 0.99,
            'motivo_confianza' => MotivoConfianza::EnTextoDelPdf,
            'legibilidad' => Legibilidad::Claro,
            'pagina' => 1,
            'recuadro' => ['x' => 0.1, 'y' => 0.2, 'ancho' => 0.3, 'alto' => 0.05],
            'evidencia' => "Texto impreso {$valor}",
            'origen' => OrigenDato::Lector,
        ]);
    }

    private function guardarPagina(): void
    {
        Storage::disk(Documento::disco())->put("lecturas/{$this->lectura->id}/pagina-1.png", Crypt::encryptString('PNG-FALSO'));
    }

    /**
     * @param  list<mixed>  $extra
     */
    private function ruta(string $nombre, array $extra = []): string
    {
        return route($nombre, [$this->cliente, $this->item, ...$extra]);
    }

    public function test_el_preparador_ve_el_visor_con_los_datos_y_lo_sensible_enmascarado(): void
    {
        $this->guardarPagina();

        $respuesta = $this->actingAs($this->preparador)->get($this->ruta('clientes.documentos.visor'));

        $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('clientes/visor')
            ->where('item.id', $this->item->id)
            ->where('item.codigo', 'w2')
            ->has('archivos', 1)
            ->has('lecturas', 1, fn (Assert $l) => $l
                ->where('id', $this->lectura->id)
                ->where('paginas', 1)
                ->has('imagenes', 1)
                ->etc())
            ->has('datos', 2)
            ->has('confianza_tabla', 6)
            ->where('umbral', 0.9)
            ->has('datos.0', fn (Assert $d) => $d
                ->where('sensible', true)
                ->where('valor', null)
                ->where('evidencia', null)
                ->where('tiene_valor', true)
                ->where('motivo_confianza.titulo', MotivoConfianza::EnTextoDelPdf->titulo())
                ->where('motivo_confianza.explicacion', MotivoConfianza::EnTextoDelPdf->explicacion())
                ->etc())
            ->has('datos.1', fn (Assert $d) => $d
                ->where('sensible', false)
                ->where('valor', '45120.00')
                ->where('evidencia', 'Texto impreso 45120.00')
                ->where('excepciones', [])
                ->etc()));

        $this->assertStringNotContainsString('6789', $respuesta->getContent());
    }

    public function test_el_visor_explica_las_excepciones_de_todo_el_documento_aunque_no_sean_de_una_casilla(): void
    {
        app()->setLocale('es');
        Excepcion::query()->create([
            'entrevista_id' => $this->entrevista->id,
            'entrevista_documento_id' => $this->item->id,
            'regla_codigo' => 'R-06',
            'severidad' => SeveridadRegla::Bloqueante,
            'clave' => 'R-06#visor',
            'detalle' => ['campo' => 'anio', 'detectado' => 2024, 'esperado' => 2025],
            'estado' => EstadoExcepcion::Abierta,
        ]);

        $this->actingAs($this->preparador)->get($this->ruta('clientes.documentos.visor'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('excepciones', 1, fn (Assert $e) => $e
                    ->where('regla_codigo', 'R-06')
                    ->where('caso', 'Este documento dice que es del 2024, pero la declaración es del 2025.')
                    ->where('severidad', 'bloqueante')
                    ->whereType('que_hacer', 'string')
                    ->whereType('regla_descripcion', 'string')
                    ->etc()));
    }

    public function test_otro_preparador_recibe_403_en_todo(): void
    {
        $otro = User::factory()->create(['role' => UserRole::Preparer]);
        $this->guardarPagina();
        $sesion = ['auth.password_confirmed_at' => time()];

        $this->actingAs($otro)->get($this->ruta('clientes.documentos.visor'))->assertForbidden();
        $this->actingAs($otro)->get($this->ruta('clientes.documentos.visor.pagina', [$this->lectura, 1]))->assertForbidden();
        $this->actingAs($otro)->withSession($sesion)->postJson($this->ruta('clientes.documentos.visor.revelar', [$this->ssn]))->assertForbidden();
        $this->actingAs($otro)->patch($this->ruta('clientes.documentos.visor.corregir', [$this->monto]), ['valor' => '1', 'motivo' => 'x'])->assertForbidden();

        $this->assertSame(0, BitacoraActividad::query()->where('campos_afectados', 'like', '%revelado%')->count());
        $this->assertSame('45120.00', $this->monto->fresh()->valor);
    }

    public function test_un_administrador_si_entra(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->get($this->ruta('clientes.documentos.visor'))->assertOk();
    }

    public function test_un_item_o_dato_de_otro_cliente_da_404(): void
    {
        $otroCliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $this->preparador->id]);
        $guardador = app(RespuestaGuardador::class);
        $otra = $guardador->entrevistaDe($otroCliente, CanalRespuesta::Portal);
        $guardador->guardar($otra, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $otroCliente);
        $ajeno = $otra->documentos()->where('documento_codigo', 'w2')->firstOrFail();
        $archivo = app(DocumentosEntrevista::class)->subir($ajeno, UploadedFile::fake()->create('w2.pdf', 10, 'application/pdf'));
        $lecturaAjena = $this->lectura($archivo, $ajeno);
        $datoAjeno = $this->dato($lecturaAjena, '1', '1.00');
        $sesion = ['auth.password_confirmed_at' => time()];
        $yo = $this->actingAs($this->preparador);

        // El ítem ajeno bajo la URL de este cliente.
        $yo->get(route('clientes.documentos.visor', [$this->cliente, $ajeno]))->assertNotFound();
        // La lectura y el dato ajenos bajo un ítem propio.
        $yo->get($this->ruta('clientes.documentos.visor.pagina', [$lecturaAjena, 1]))->assertNotFound();
        $yo->withSession($sesion)->postJson($this->ruta('clientes.documentos.visor.revelar', [$datoAjeno]))->assertNotFound();
        $yo->patch($this->ruta('clientes.documentos.visor.corregir', [$datoAjeno]), ['valor' => '2', 'motivo' => 'x'])->assertNotFound();
        $this->assertSame('1.00', $datoAjeno->fresh()->valor);
    }

    public function test_la_imagen_se_descifra_al_vuelo_sin_cache_y_sin_permiso_es_403(): void
    {
        $this->guardarPagina();

        $respuesta = $this->actingAs($this->preparador)->get($this->ruta('clientes.documentos.visor.pagina', [$this->lectura, 1]));

        $respuesta->assertOk();
        $this->assertSame('PNG-FALSO', $respuesta->getContent());
        $this->assertSame('image/png', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $respuesta->headers->get('Cache-Control'));

        $this->assertNotSame('PNG-FALSO', Storage::disk(Documento::disco())->get("lecturas/{$this->lectura->id}/pagina-1.png"));

        $this->actingAs($this->cliente)->get($this->ruta('clientes.documentos.visor.pagina', [$this->lectura, 1]))->assertForbidden();
    }

    public function test_una_pagina_que_no_existe_da_404(): void
    {
        $yo = $this->actingAs($this->preparador);

        $yo->get($this->ruta('clientes.documentos.visor.pagina', [$this->lectura, 1]))->assertNotFound(); // sin archivo en disco
        $this->guardarPagina();
        $yo->get($this->ruta('clientes.documentos.visor.pagina', [$this->lectura, 2]))->assertNotFound();
        $yo->get($this->ruta('clientes.documentos.visor.pagina', [$this->lectura, 0]))->assertNotFound();
    }

    public function test_revelar_exige_contrasena_y_queda_en_la_bitacora_sin_el_valor(): void
    {
        $this->actingAs($this->preparador)
            ->postJson($this->ruta('clientes.documentos.visor.revelar', [$this->ssn]))
            ->assertStatus(423);

        $this->actingAs($this->preparador)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson($this->ruta('clientes.documentos.visor.revelar', [$this->ssn]))
            ->assertOk()
            ->assertJson(['valor' => self::SSN]);

        $evento = BitacoraActividad::query()->where('auditable_type', DocumentoDato::class)->where('auditable_id', $this->ssn->id)->where('accion', AccionAuditoria::Actualizado)->sole();
        $this->assertSame($this->preparador->id, $evento->actor_id);
        $this->assertSame(['revelado'], $evento->campos_afectados);
        $this->assertStringNotContainsString('6789', json_encode($evento->getAttributes()));
    }

    public function test_corregir_guarda_el_historial_reabre_lo_validado_y_corre_las_reglas(): void
    {
        app(ChecklistDocumentos::class)->validar($this->item->fresh(), $this->preparador);
        $this->assertSame(EstadoDocumento::Validado, $this->item->fresh()->estado);

        $this->actingAs($this->preparador)
            ->patch($this->ruta('clientes.documentos.visor.corregir', [$this->monto]), ['valor' => '45210.00', 'motivo' => 'Dígitos cambiados.'])
            ->assertRedirect();

        $this->assertSame('45210.00', $this->monto->fresh()->valor);
        $this->assertSame('45120.00', $this->monto->fresh()->valor_leido);
        $this->assertSame(EstadoDocumento::Leido, $this->item->fresh()->estado);
        $cambio = DocumentoDatoCambio::query()->where('dato_id', $this->monto->id)->sole();
        $this->assertSame($this->preparador->id, $cambio->actor_id);
        $this->assertSame('Dígitos cambiados.', $cambio->motivo);
    }

    public function test_corregir_valida_el_motivo_y_el_largo(): void
    {
        $yo = $this->actingAs($this->preparador);
        $url = $this->ruta('clientes.documentos.visor.corregir', [$this->monto]);

        $yo->patch($url, ['valor' => '1'])->assertSessionHasErrors('motivo');
        $yo->patch($url, ['valor' => '1', 'motivo' => '   '])->assertSessionHasErrors('motivo');
        $yo->patch($url, ['valor' => str_repeat('9', 256), 'motivo' => 'x'])->assertSessionHasErrors('valor');
        $yo->patch($url, ['valor' => '1', 'motivo' => str_repeat('x', 501)])->assertSessionHasErrors('motivo');

        $this->assertSame('45120.00', $this->monto->fresh()->valor);
        $this->assertSame(0, DocumentoDatoCambio::query()->count());
    }

    public function test_el_cliente_no_entra_al_visor(): void
    {
        $this->actingAs($this->cliente)->get($this->ruta('clientes.documentos.visor'))->assertForbidden();
    }
}
