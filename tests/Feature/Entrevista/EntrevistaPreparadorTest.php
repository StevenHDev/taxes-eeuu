<?php

namespace Tests\Feature\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\Entrevista\EntrevistaRespuestaReveal;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * F2.9: la entrevista en la ficha del cliente. El preparador la ve solo
 * para sus clientes, lo sensible va oculto y se revela con contraseña
 * (queda registrado), y corregir guarda con canal `preparador` y reabre.
 */
class EntrevistaPreparadorTest extends TestCase
{
    use RefreshDatabase;

    private User $preparador;

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        $this->preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $this->preparador->id]);

        $guardador = app(RespuestaGuardador::class);
        $this->entrevista = $guardador->entrevistaDe($this->cliente, CanalRespuesta::Portal);
        $guardador->guardar($this->entrevista, new RespuestaData('A3', 'itin'), CanalRespuesta::Portal, $this->cliente);
        $guardador->guardar($this->entrevista, new RespuestaData('B1', 'nunca_casado'), CanalRespuesta::Whatsapp, null);
    }

    private function respuesta(string $codigo): EntrevistaRespuesta
    {
        return $this->entrevista->respuestas()->where('pregunta_codigo', $codigo)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function pregunta(array $entrevista, string $codigo): array
    {
        return collect($entrevista['pantallas'])->flatMap(fn (array $p) => $p['preguntas'])->firstWhere('codigo', $codigo);
    }

    public function test_el_preparador_ve_la_entrevista_con_canal_y_fecha_y_lo_sensible_oculto(): void
    {
        $this->actingAs($this->preparador)
            ->get(route('clientes.ficha.entrevista', $this->cliente))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ficha/entrevista')
                ->missing('entrevista')
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where('entrevista.estado', 'en_curso')
                    ->where('entrevista.formularios', fn ($f) => collect($f)->contains('1040'))
                    ->where('entrevista', function ($entrevista) {
                        $entrevista = collect($entrevista)->toArray();
                        $a3 = $this->pregunta($entrevista, 'A3');
                        $b1 = $this->pregunta($entrevista, 'B1');

                        return $a3['sensible'] && $a3['respondida'] && $a3['valor'] === null && $a3['canal'] === 'portal'
                            && $b1['valor'] === 'nunca_casado' && $b1['canal'] === 'whatsapp' && $b1['respondida_en'] !== null;
                    })));
    }

    public function test_muestra_el_consentimiento_firmado_del_anio(): void
    {
        app(ConsentimientosCargador::class)->cargar();
        app(Consentimientos::class)->firmar($this->cliente, $this->entrevista->tax_year, 1, 'Cliente', null, null);

        $this->actingAs($this->preparador)
            ->get(route('clientes.ficha.entrevista', $this->cliente))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where('entrevista.consentimiento.version', 1)
                    ->where('entrevista.consentimiento.provisional', true)));
    }

    public function test_otro_preparador_no_ve_ni_toca_la_entrevista(): void
    {
        $otro = User::factory()->create(['role' => UserRole::Preparer]);
        $a3 = $this->respuesta('A3');

        $this->actingAs($otro)->get(route('clientes.show', $this->cliente))->assertForbidden();
        $this->actingAs($otro)->getJson(route('clientes.entrevista.respuestas.historial', [$this->cliente, $a3]))->assertForbidden();
        $this->actingAs($otro)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('clientes.entrevista.respuestas.revelar', [$this->cliente, $a3]))->assertForbidden();
        $this->actingAs($otro)->put(route('clientes.entrevista.respuestas.update', [$this->cliente, $a3]), ['valor' => 'ssn_valido'])->assertForbidden();

        $this->assertSame(0, EntrevistaRespuestaReveal::query()->count());
        $this->assertSame('itin', $a3->fresh()->valor);
    }

    public function test_una_respuesta_de_otro_cliente_no_se_alcanza_por_la_url_de_este(): void
    {
        $otroCliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $this->preparador->id]);
        $otra = app(RespuestaGuardador::class)->entrevistaDe($otroCliente, CanalRespuesta::Portal);
        $ajena = app(RespuestaGuardador::class)->guardar($otra, new RespuestaData('A3', 'ssn_valido'), CanalRespuesta::Portal, $otroCliente);

        $this->actingAs($this->preparador)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('clientes.entrevista.respuestas.revelar', [$this->cliente, $ajena]))->assertNotFound();
        $this->actingAs($this->preparador)
            ->put(route('clientes.entrevista.respuestas.update', [$this->cliente, $ajena]), ['valor' => 'itin'])->assertForbidden();
    }

    public function test_revelar_exige_contrasena_y_queda_registrado(): void
    {
        $a3 = $this->respuesta('A3');

        $this->actingAs($this->preparador)
            ->postJson(route('clientes.entrevista.respuestas.revelar', [$this->cliente, $a3]))
            ->assertStatus(423);
        $this->assertSame(0, EntrevistaRespuestaReveal::query()->count());

        $this->actingAs($this->preparador)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('clientes.entrevista.respuestas.revelar', [$this->cliente, $a3]))
            ->assertOk()
            ->assertExactJson(['valor' => 'itin']);

        $this->assertDatabaseHas('entrevista_respuesta_reveals', ['respuesta_id' => $a3->id, 'revealed_by_id' => $this->preparador->id]);
    }

    public function test_corregir_guarda_con_canal_preparador_reabre_y_queda_en_el_historial(): void
    {
        $this->entrevista->update(['estado' => EstadoEntrevista::Confirmada, 'confirmada_en' => now()]);
        $b1 = $this->respuesta('B1');

        $this->actingAs($this->preparador)
            ->from(route('clientes.show', $this->cliente))
            ->put(route('clientes.entrevista.respuestas.update', [$this->cliente, $b1]), ['valor' => 'casado'])
            ->assertRedirect(route('clientes.show', $this->cliente))
            ->assertSessionHasNoErrors();

        $b1->refresh();
        $this->assertSame('casado', $b1->valor);
        $this->assertSame(CanalRespuesta::Preparador, $b1->canal);
        $this->assertSame($this->preparador->id, $b1->actor_id);
        $this->assertSame(EstadoEntrevista::Reabierta, $this->entrevista->fresh()->estado);

        $cambios = $this->actingAs($this->preparador)
            ->getJson(route('clientes.entrevista.respuestas.historial', [$this->cliente, $b1]))
            ->assertOk()
            ->json('cambios');

        $this->assertSame(['casado', 'nunca_casado'], [$cambios[0]['valor_nuevo'], $cambios[0]['valor_anterior']]);
        $this->assertSame(['preparador', $this->preparador->name], [$cambios[0]['canal'], $cambios[0]['actor']]);
    }

    public function test_un_valor_que_no_calza_vuelve_como_error_y_no_se_guarda(): void
    {
        $b1 = $this->respuesta('B1');

        $this->actingAs($this->preparador)
            ->put(route('clientes.entrevista.respuestas.update', [$this->cliente, $b1]), ['valor' => 'tal vez'])
            ->assertSessionHasErrors('valor');

        $this->assertSame('nunca_casado', $b1->fresh()->valor);
    }

    public function test_el_historial_de_una_sensible_no_muestra_valores(): void
    {
        app(RespuestaGuardador::class)->guardar($this->entrevista, new RespuestaData('A3', 'ssn_valido'), CanalRespuesta::Portal, $this->cliente);

        $cambios = $this->actingAs($this->preparador)
            ->getJson(route('clientes.entrevista.respuestas.historial', [$this->cliente, $this->respuesta('A3')]))
            ->assertOk()
            ->assertDontSee('itin')
            ->assertDontSee('ssn_valido')
            ->json('cambios');

        // La primera respuesta no deja historial (RespuestaEntrevistaObserver).
        $this->assertCount(1, $cambios);
        $this->assertTrue($cambios[0]['sensible']);
    }

    public function test_un_cliente_sin_entrevista_trae_null(): void
    {
        $nuevo = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $this->preparador->id]);

        $this->actingAs($this->preparador)
            ->get(route('clientes.ficha.entrevista', $nuevo))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload->where('entrevista', null)));
    }
}
