<?php

namespace Tests\Feature\Lectura;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\VistaEntrevistaPreparador;
use App\Services\WhatsappAgent\HerramientasEntrevista;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F4.7a: la cola de excepciones del preparador y sus salidas (aceptar con
 * nota, consultar al cliente). Nunca sale un valor leído, un SSN ni un monto.
 */
class ColaExcepcionesTest extends TestCase
{
    use RefreshDatabase;

    private User $preparador;

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);

        $this->preparador = User::factory()->create(['role' => UserRole::Preparer]);
        [$this->cliente, $this->entrevista] = $this->clienteConEntrevista($this->preparador);
    }

    /**
     * @return array{0: User, 1: Entrevista}
     */
    private function clienteConEntrevista(User $preparador): array
    {
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $guardador = app(RespuestaGuardador::class);
        $entrevista = $guardador->entrevistaDe($cliente, CanalRespuesta::Portal);
        $guardador->guardar($entrevista, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $cliente);

        return [$cliente, $entrevista];
    }

    private function item(Entrevista $entrevista, EstadoDocumento $estado = EstadoDocumento::Leido): EntrevistaDocumento
    {
        $item = $entrevista->documentos()->where('documento_codigo', 'w2')->first()
            ?? $entrevista->documentos()->create(['documento_codigo' => 'w2', 'numero' => 1, 'estado' => $estado]);
        $item->update(['estado' => $estado]);

        return $item;
    }

    private function excepcion(Entrevista $entrevista, string $regla = 'R-04', SeveridadRegla $severidad = SeveridadRegla::Bloqueante, ?EntrevistaDocumento $item = null, array $detalle = []): Excepcion
    {
        return Excepcion::query()->create([
            'entrevista_id' => $entrevista->id,
            'entrevista_documento_id' => $item?->id,
            'regla_codigo' => $regla,
            'severidad' => $severidad,
            'clave' => $regla.'|'.($item?->id ?? 0).'|'.uniqid(),
            'detalle' => $detalle,
            'estado' => EstadoExcepcion::Abierta,
        ]);
    }

    public function test_la_lista_va_por_severidad_y_despues_por_fecha_con_lo_que_pide_la_pantalla(): void
    {
        $item = $this->item($this->entrevista);
        $informativa = $this->excepcion($this->entrevista, 'R-07', SeveridadRegla::Informativa, $item);
        $revision = $this->excepcion($this->entrevista, 'R-03', SeveridadRegla::Revision, $item, ['casilla' => '1', 'motivo_confianza' => MotivoConfianza::Dudoso->value, 'confianza' => 0.5]);
        $bloqueante = $this->excepcion($this->entrevista, 'R-04', SeveridadRegla::Bloqueante, $item);
        $corregida = $this->excepcion($this->entrevista, 'R-06', SeveridadRegla::Bloqueante, $item);
        $corregida->update(['estado' => EstadoExcepcion::Corregida]);

        $this->actingAs($this->preparador)->get(route('clientes.excepciones.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('excepciones/index', false)
                ->has('excepciones', 3)
                ->where('excepciones.0.id', $bloqueante->id)
                ->where('excepciones.1.id', $revision->id)
                ->where('excepciones.2.id', $informativa->id)
                ->where('excepciones.0.regla.codigo', 'R-04')
                ->has('excepciones.0.regla.descripcion')
                ->where('excepciones.0.cliente', ['id' => $this->cliente->id, 'nombre' => $this->cliente->name])
                ->where('excepciones.0.item.documento_codigo', 'w2')
                ->where('excepciones.0.motivo_confianza', null)
                ->where('excepciones.1.casilla', '1')
                ->where('excepciones.1.motivo_confianza.valor', 'dudoso')
                ->where('excepciones.1.motivo_confianza.titulo', MotivoConfianza::Dudoso->titulo())
                ->where('excepciones.0.enlaces.aceptar', route('clientes.excepciones.aceptar', $bloqueante))
                ->has('clientes', 1)
                ->where('filtros.severidad', null));
    }

    public function test_el_enlace_del_visor_solo_va_si_la_ruta_existe(): void
    {
        $item = $this->item($this->entrevista);
        $this->excepcion($this->entrevista, 'R-04', SeveridadRegla::Bloqueante, $item);

        $esperado = Route::has('clientes.documentos.visor')
            ? route('clientes.documentos.visor', ['cliente' => $this->cliente->id, 'item' => $item->id])
            : null;

        $this->actingAs($this->preparador)->get(route('clientes.excepciones.index'))
            ->assertInertia(fn (Assert $page) => $page->where('excepciones.0.enlaces.corregir', $esperado));
    }

    public function test_los_filtros_por_severidad_estado_y_cliente(): void
    {
        $item = $this->item($this->entrevista);
        $this->excepcion($this->entrevista, 'R-04', SeveridadRegla::Bloqueante, $item);
        $consultada = $this->excepcion($this->entrevista, 'R-03', SeveridadRegla::Revision, $item);
        $consultada->update(['estado' => EstadoExcepcion::Consultada]);
        [$otro, $entrevistaOtro] = $this->clienteConEntrevista($this->preparador);
        $this->excepcion($entrevistaOtro);

        $this->actingAs($this->preparador);

        $this->get(route('clientes.excepciones.index', ['severidad' => 'revision']))
            ->assertInertia(fn (Assert $page) => $page->has('excepciones', 1)->where('excepciones.0.id', $consultada->id));
        $this->get(route('clientes.excepciones.index', ['estado' => 'consultada']))
            ->assertInertia(fn (Assert $page) => $page->has('excepciones', 1)->where('excepciones.0.id', $consultada->id));
        $this->get(route('clientes.excepciones.index', ['cliente' => $otro->id]))
            ->assertInertia(fn (Assert $page) => $page->has('excepciones', 1)->where('excepciones.0.cliente.id', $otro->id)->has('clientes', 2));
    }

    public function test_un_preparador_no_ve_ni_resuelve_las_de_clientes_de_otro(): void
    {
        $ajeno = User::factory()->create(['role' => UserRole::Preparer]);
        $excepcion = $this->excepcion($this->entrevista, 'R-04', SeveridadRegla::Bloqueante, $this->item($this->entrevista));

        $this->actingAs($ajeno)->get(route('clientes.excepciones.index'))
            ->assertInertia(fn (Assert $page) => $page->has('excepciones', 0));
        $this->actingAs($ajeno)->post(route('clientes.excepciones.aceptar', $excepcion), ['nota' => 'ok'])->assertForbidden();
        $this->actingAs($ajeno)->post(route('clientes.excepciones.consultar', $excepcion), ['texto' => 'ok'])->assertForbidden();
        $this->actingAs($this->cliente)->post(route('clientes.excepciones.aceptar', $excepcion), ['nota' => 'ok'])->assertForbidden();
        $this->actingAs($this->cliente)->get(route('clientes.excepciones.index'))->assertForbidden();

        $this->assertSame(EstadoExcepcion::Abierta, $excepcion->fresh()->estado);

        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($admin)->get(route('clientes.excepciones.index'))
            ->assertInertia(fn (Assert $page) => $page->has('excepciones', 1));
    }

    public function test_aceptar_con_nota_cambia_el_estado_y_queda_en_la_bitacora(): void
    {
        $excepcion = $this->excepcion($this->entrevista);

        $this->actingAs($this->preparador)
            ->post(route('clientes.excepciones.aceptar', $excepcion), ['nota' => 'El cliente trajo la corrección.'])
            ->assertRedirect();

        $excepcion->refresh();
        $this->assertSame(EstadoExcepcion::Aceptada, $excepcion->estado);
        $this->assertSame($this->preparador->id, $excepcion->resuelta_por);
        $this->assertNotNull($excepcion->resuelta_en);
        $this->assertSame('El cliente trajo la corrección.', $excepcion->nota);

        $registro = BitacoraActividad::query()
            ->where('auditable_type', Excepcion::class)->where('auditable_id', $excepcion->id)
            ->where('accion', 'actualizado')->latest('id')->firstOrFail();
        $this->assertSame($this->preparador->id, $registro->actor_id);
        $this->assertContains('estado', $registro->campos_afectados);
        $this->assertStringNotContainsString('El cliente trajo', json_encode($registro->toArray()));

        $this->actingAs($this->preparador)->get(route('clientes.excepciones.index'))
            ->assertInertia(fn (Assert $page) => $page->has('excepciones', 0));
    }

    public function test_aceptar_exige_nota_y_una_excepcion_abierta(): void
    {
        $excepcion = $this->excepcion($this->entrevista);

        $this->actingAs($this->preparador)->post(route('clientes.excepciones.aceptar', $excepcion), ['nota' => ''])
            ->assertSessionHasErrors('nota');

        $excepcion->update(['estado' => EstadoExcepcion::Corregida]);
        $this->actingAs($this->preparador)->post(route('clientes.excepciones.aceptar', $excepcion), ['nota' => 'tarde'])
            ->assertSessionHasErrors('nota');
        $this->assertSame(EstadoExcepcion::Corregida, $excepcion->fresh()->estado);
    }

    public function test_consultar_devuelve_el_documento_al_cliente_y_el_agente_lo_ve(): void
    {
        $item = $this->item($this->entrevista);
        $excepcion = $this->excepcion($this->entrevista, 'R-04', SeveridadRegla::Bloqueante, $item);

        $this->actingAs($this->preparador)
            ->post(route('clientes.excepciones.consultar', $excepcion), ['texto' => 'Esta foto no se ve, ¿la subes de nuevo?'])
            ->assertRedirect();

        $this->assertSame(EstadoExcepcion::Consultada, $excepcion->fresh()->estado);
        $this->assertSame(EstadoDocumento::ConObservacion, $item->fresh()->estado);
        $this->assertSame('Esta foto no se ve, ¿la subes de nuevo?', $item->fresh()->observacion);

        $this->assertTrue(BitacoraActividad::query()
            ->where('auditable_type', Excepcion::class)->where('auditable_id', $excepcion->id)
            ->where('actor_id', $this->preparador->id)->exists());

        $pendientes = collect(app(HerramientasEntrevista::class)->consultar($this->cliente)['documentos']['pendientes']);
        $w2 = $pendientes->firstWhere('nombre_oficial', 'Form W-2');
        $this->assertTrue($w2['devuelto_por_el_preparador']);
        $this->assertSame('Esta foto no se ve, ¿la subes de nuevo?', $w2['comentario_del_preparador']);

        // Sigue abierta (consultada): una segunda consulta es posible.
        $this->actingAs($this->preparador)->get(route('clientes.excepciones.index'))
            ->assertInertia(fn (Assert $page) => $page->where('excepciones.0.estado', 'consultada'));
    }

    public function test_consultar_no_deja_la_excepcion_a_medias_si_el_item_no_se_puede_devolver(): void
    {
        $item = $this->item($this->entrevista, EstadoDocumento::Pendiente);
        $excepcion = $this->excepcion($this->entrevista, 'R-04', SeveridadRegla::Bloqueante, $item);

        $this->actingAs($this->preparador)
            ->post(route('clientes.excepciones.consultar', $excepcion), ['texto' => 'Pregunta'])
            ->assertSessionHasErrors('texto');

        $this->assertSame(EstadoExcepcion::Abierta, $excepcion->fresh()->estado);
        $this->assertSame(EstadoDocumento::Pendiente, $item->fresh()->estado);
    }

    public function test_una_excepcion_sin_item_no_se_puede_consultar(): void
    {
        $excepcion = $this->excepcion($this->entrevista, 'R-09');

        $this->actingAs($this->preparador)
            ->post(route('clientes.excepciones.consultar', $excepcion), ['texto' => 'Pregunta'])
            ->assertSessionHasErrors('texto');
        $this->actingAs($this->preparador)
            ->post(route('clientes.excepciones.consultar', $excepcion), ['texto' => ''])
            ->assertSessionHasErrors('texto');

        $this->assertSame(EstadoExcepcion::Abierta, $excepcion->fresh()->estado);
    }

    public function test_la_ficha_trae_el_conteo_de_abiertas_y_bloqueantes_sin_valores(): void
    {
        $item = $this->item($this->entrevista);
        $this->excepcion($this->entrevista, 'R-04', SeveridadRegla::Bloqueante, $item, ['casilla' => '2', 'valor' => '123-45-6789']);
        $this->excepcion($this->entrevista, 'R-03', SeveridadRegla::Revision, $item);
        $this->excepcion($this->entrevista, 'R-07', SeveridadRegla::Bloqueante, $item)->update(['estado' => EstadoExcepcion::Aceptada]);

        $vista = app(VistaEntrevistaPreparador::class)->para($this->cliente, $this->entrevista->tax_year, false);

        $this->assertSame(['total' => 2, 'bloqueantes' => 1], $vista['excepciones_abiertas']);

        $lista = $this->actingAs($this->preparador)->get(route('clientes.excepciones.index'))->getContent();
        $this->assertStringNotContainsString('123-45-6789', $lista);
    }
}
