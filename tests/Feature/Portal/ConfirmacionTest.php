<?php

namespace Tests\Feature\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\UserRole;
use App\Models\ClienteAtestacion;
use App\Models\Entrevista\Entrevista;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * F2.7: revisión y confirmación de la entrevista en el portal.
 */
class ConfirmacionTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        app(ConsentimientosCargador::class)->cargar();
        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        $this->cliente->forceFill(['entrevista_catalogo' => true])->save();
        app(Consentimientos::class)->firmar($this->cliente, (int) config('tax.current_tax_year'), 1, 'Cliente', null, null);
    }

    private function entrevista(): Entrevista
    {
        return app(RespuestaGuardador::class)->entrevistaDe($this->cliente, CanalRespuesta::Portal)->fresh();
    }

    /**
     * Responde todo lo visible con un valor válido ("no" cuando se puede,
     * para no abrir seguimientos), hasta que no falte nada.
     */
    private function responderTodo(): void
    {
        $preguntas = app(CatalogoFiscal::class)->preguntas((int) config('tax.current_tax_year'))->keyBy('codigo');

        for ($vuelta = 0; $vuelta < 5; $vuelta++) {
            $situacion = app(MotorEntrevista::class)->situacion($this->entrevista());

            if ($situacion->completa()) {
                return;
            }

            foreach ($situacion->pantallas as $pantalla) {
                foreach ($pantalla['preguntas'] as $p) {
                    if ($p['respondida']) {
                        continue;
                    }

                    $pregunta = $preguntas[$p['codigo']];
                    $valor = match ($pregunta['tipo_respuesta']) {
                        'si_no', 'usted_conyuge_no' => 'no',
                        'opciones' => $pregunta['opciones'][0]['valor'],
                        'casillas' => [$pregunta['opciones'][0]['valor']],
                        'fecha' => '1990-01-01',
                        'numero' => 1,
                        default => 'Dato de prueba',
                    };

                    app(RespuestaGuardador::class)->guardar($this->entrevista(), new RespuestaData($p['codigo'], $valor, $p['persona_id']), CanalRespuesta::Portal, $this->cliente);
                }
            }
        }
    }

    public function test_con_preguntas_pendientes_no_se_confirma(): void
    {
        $this->actingAs($this->cliente)->get(route('portal.entrevista.resumen'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('portal/entrevista/resumen')->where('estado', 'en_curso'));

        $this->actingAs($this->cliente)->post(route('portal.entrevista.confirmar'))->assertSessionHasErrors('confirmar');

        $this->assertSame(0, ClienteAtestacion::query()->count());
    }

    public function test_con_todo_respondido_se_confirma_aunque_falten_documentos(): void
    {
        $this->responderTodo();

        $this->actingAs($this->cliente)->post(route('portal.entrevista.confirmar'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portal.entrevista.resumen'));

        $atestacion = ClienteAtestacion::query()->sole();
        $this->assertSame('portal', $atestacion->canal);
        $this->assertSame($this->entrevista()->id, $atestacion->entrevista_id);
        $this->assertSame(64, strlen((string) $atestacion->resumen_hash));
        $this->assertSame(EstadoEntrevista::Confirmada, $this->entrevista()->estado);
        $this->assertNotNull($this->entrevista()->confirmada_en);
    }

    public function test_cambiar_una_respuesta_reabre_y_la_confirmacion_vieja_se_conserva(): void
    {
        $this->responderTodo();
        $this->actingAs($this->cliente)->post(route('portal.entrevista.confirmar'));
        $hash = ClienteAtestacion::query()->sole()->resumen_hash;

        app(RespuestaGuardador::class)->guardar($this->entrevista(), new RespuestaData('D7', 'si'), CanalRespuesta::Whatsapp, $this->cliente);
        $this->assertSame(EstadoEntrevista::Reabierta, $this->entrevista()->estado);
        $this->actingAs($this->cliente)->get(route('portal.entrevista.resumen'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('estado', 'reabierta'));

        $this->actingAs($this->cliente)->post(route('portal.entrevista.confirmar'))->assertSessionHasNoErrors();

        $this->assertSame(2, ClienteAtestacion::query()->count());
        $this->assertNotSame($hash, ClienteAtestacion::query()->latest('id')->first()->resumen_hash);
        $this->assertSame(EstadoEntrevista::Confirmada, $this->entrevista()->estado);
    }

    public function test_despues_de_la_ultima_pantalla_va_al_resumen(): void
    {
        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.guardar', ['pantalla' => 8, 'paso' => 'entrada']), ['respuestas' => ['G1' => 'no', 'G2' => 'no', 'G5' => 'si']])
            ->assertRedirect(route('portal.entrevista.resumen'));
    }

    public function test_el_resumen_lleva_cada_respuesta_a_su_paso_y_marca_las_sensibles(): void
    {
        app(RespuestaGuardador::class)->guardar($this->entrevista(), new RespuestaData('B1', 'casado'), CanalRespuesta::Portal, $this->cliente);

        $this->actingAs($this->cliente)->get(route('portal.entrevista.resumen'))
            ->assertInertia(function (AssertableInertia $page) {
                $preguntas = collect($page->toArray()['props']['pantallas'])->flatMap(fn (array $p) => $p['preguntas'])->keyBy('codigo');

                $this->assertSame(route('portal.entrevista.paso', ['pantalla' => 2, 'paso' => 'entrada']), $preguntas['B1']['url']);
                $this->assertSame(route('portal.entrevista.paso', ['pantalla' => 2, 'paso' => 'seguimiento']), $preguntas['B4.3']['url']);
                $this->assertTrue($preguntas['B4.3']['sensible']);
                $this->assertSame('casado', $preguntas['B1']['valor']);
            });
    }
}
