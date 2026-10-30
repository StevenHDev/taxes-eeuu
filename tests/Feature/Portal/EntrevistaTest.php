<?php

namespace Tests\Feature\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
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
 * F2.5: la entrevista en el portal, un paso por vez, con las preguntas que
 * decide el servidor.
 */
class EntrevistaTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        app(ConsentimientosCargador::class)->cargar();

        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true, 'phone' => '+15550003333']);
        $this->cliente->forceFill(['entrevista_catalogo' => true])->save();
        app(Consentimientos::class)->firmar($this->cliente, (int) config('tax.current_tax_year'), 1, 'Cliente', null, null);
    }

    private function entrevista(): Entrevista
    {
        return app(RespuestaGuardador::class)->entrevistaDe($this->cliente, CanalRespuesta::Portal)->fresh();
    }

    private function respuesta(string $codigo, ?EntrevistaPersona $persona = null): mixed
    {
        return $this->entrevista()->respuestas()
            ->where('pregunta_codigo', $codigo)
            ->where('persona_id', $persona?->id)
            ->first()?->valor;
    }

    /**
     * @return list<string>
     */
    private function codigosDelPaso(string $url): array
    {
        $codigos = [];
        $this->actingAs($this->cliente)->get($url)->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$codigos) {
                $codigos = collect($page->toArray()['props']['preguntas'])->pluck('codigo')->all();
            });

        return $codigos;
    }

    public function test_sin_la_bandera_sigue_el_formulario_actual(): void
    {
        $otro = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($otro)->get(route('portal.entrevista.inicio'))->assertRedirect(route('portal.formulario'));
        $this->actingAs($otro)->get('/portal')->assertRedirect(route('portal.formulario'));

        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($admin)->get(route('portal.entrevista.inicio'))->assertRedirect(route('dashboard'));
    }

    public function test_con_la_bandera_el_portal_y_el_panel_llevan_a_la_entrevista(): void
    {
        $this->actingAs($this->cliente)->get('/portal')->assertRedirect(route('portal.entrevista.inicio'));
        $this->actingAs($this->cliente)->get(route('dashboard'))->assertRedirect(route('portal.entrevista.inicio'));
        $this->actingAs($this->cliente)->get(route('portal.formulario'))->assertRedirect(route('portal.entrevista.inicio'));

        $this->actingAs($this->cliente)->get(route('portal.entrevista.inicio'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/entrevista/inicio')
                ->has('pantallas', 8)
                ->where('continuarEn', 1));
    }

    public function test_sin_consentimiento_la_entrevista_pide_firmar(): void
    {
        $nuevo = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        $nuevo->forceFill(['entrevista_catalogo' => true])->save();

        $this->actingAs($nuevo)->get(route('portal.entrevista.paso', ['pantalla' => 5]))->assertRedirect(route('portal.consentimiento'));
    }

    public function test_el_paso_solo_trae_sus_preguntas(): void
    {
        $this->assertSame(['B1', 'B5'], $this->codigosDelPaso(route('portal.entrevista.paso', ['pantalla' => 2])));
        $this->assertSame(['C0'], $this->codigosDelPaso(route('portal.entrevista.paso', ['pantalla' => 4])));

        $pantalla5 = $this->codigosDelPaso(route('portal.entrevista.paso', ['pantalla' => 5]));
        $this->assertContains('D1', $pantalla5);
        $this->assertContains('D18', $pantalla5);
        $this->assertNotContains('D1.1', $pantalla5, 'D1.1 es seguimiento.');
        $this->assertNotContains('B1', $pantalla5);
    }

    public function test_guardar_la_pantalla_5_marca_si_lo_elegido_no_lo_demas_y_lleva_al_seguimiento(): void
    {
        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.guardar', ['pantalla' => 5, 'paso' => 'entrada']), ['respuestas' => ['D1' => 'si', 'D15' => 'si']])
            ->assertRedirect(route('portal.entrevista.paso', ['pantalla' => 5, 'paso' => 'seguimiento']));

        $this->assertSame('si', $this->respuesta('D1'));
        $this->assertSame('si', $this->respuesta('D15'));
        $this->assertSame('no', $this->respuesta('D7'));
        $this->assertSame('no', $this->respuesta('D18'));
        $this->assertSame(['D1.1', 'D15.1'], $this->codigosDelPaso(route('portal.entrevista.paso', ['pantalla' => 5, 'paso' => 'seguimiento'])));

        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.guardar', ['pantalla' => 5, 'paso' => 'seguimiento']), ['respuestas' => ['D1.1' => '2']])
            ->assertRedirect(route('portal.entrevista.paso', ['pantalla' => 6]));
        $this->assertSame(2, $this->respuesta('D1.1'));
    }

    public function test_ninguna_guarda_todo_en_no_y_salta_el_seguimiento(): void
    {
        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.guardar', ['pantalla' => 5, 'paso' => 'entrada']), ['respuestas' => []])
            ->assertRedirect(route('portal.entrevista.paso', ['pantalla' => 6]));

        $this->assertSame('no', $this->respuesta('D1'));
        // Las 18 de entrada del bloque D (D1–D18), todas en "no".
        $this->assertSame(18, $this->entrevista()->respuestas()->where('pregunta_codigo', 'like', 'D%')->count());
    }

    public function test_un_codigo_ajeno_al_paso_o_un_valor_invalido_devuelve_error_y_no_guarda_nada(): void
    {
        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.guardar', ['pantalla' => 5, 'paso' => 'entrada']), ['respuestas' => ['B1' => 'casado', 'D1' => 'si']])
            ->assertSessionHasErrors('respuestas');

        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.guardar', ['pantalla' => 2, 'paso' => 'entrada']), ['respuestas' => ['B1' => 'casada']])
            ->assertSessionHasErrors('respuestas.B1');

        $this->assertSame(0, $this->entrevista()->respuestas()->count());
    }

    public function test_casado_abre_el_seguimiento_de_la_pantalla_2_y_la_3_muestra_al_conyuge(): void
    {
        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.guardar', ['pantalla' => 2, 'paso' => 'entrada']), ['respuestas' => ['B1' => 'casado', 'B5' => 'si']])
            ->assertRedirect(route('portal.entrevista.paso', ['pantalla' => 2, 'paso' => 'seguimiento']));

        $this->assertSame(['B2', 'B3', 'B4'], $this->codigosDelPaso(route('portal.entrevista.paso', ['pantalla' => 2, 'paso' => 'seguimiento'])));

        $this->actingAs($this->cliente)->get(route('portal.entrevista.paso', ['pantalla' => 3]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conConyuge', true)
                ->where('preguntas.0.codigo', 'A5'));
    }

    public function test_c0_si_lleva_a_las_personas_y_un_padre_ve_c12_a_c14(): void
    {
        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.guardar', ['pantalla' => 4, 'paso' => 'entrada']), ['respuestas' => ['C0' => 'si']])
            ->assertRedirect(route('portal.entrevista.personas'));

        $this->actingAs($this->cliente)->post(route('portal.entrevista.personas.store'))->assertRedirect();
        $persona = EntrevistaPersona::query()->sole();

        $this->actingAs($this->cliente)
            ->put(route('portal.entrevista.persona.guardar', ['persona' => $persona->id, 'paso' => 'entrada']), ['respuestas' => ['C1' => 'Pedro', 'C3' => 'padre', 'C4' => '12']])
            ->assertRedirect(route('portal.entrevista.persona', ['persona' => $persona->id, 'paso' => 'seguimiento']));

        $this->assertSame(['C12', 'C13', 'C14'], $this->codigosDelPaso(route('portal.entrevista.persona', ['persona' => $persona->id, 'paso' => 'seguimiento'])));
        $this->assertSame('padre', $this->respuesta('C3', $persona));
    }

    public function test_quitar_una_persona_la_archiva_y_otra_cuenta_no_puede_verla(): void
    {
        $this->actingAs($this->cliente)->put(route('portal.entrevista.guardar', ['pantalla' => 4, 'paso' => 'entrada']), ['respuestas' => ['C0' => 'si']]);
        $this->actingAs($this->cliente)->post(route('portal.entrevista.personas.store'));
        $persona = EntrevistaPersona::query()->sole();

        $otro = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        $otro->forceFill(['entrevista_catalogo' => true])->save();
        app(Consentimientos::class)->firmar($otro, (int) config('tax.current_tax_year'), 1, 'Otro', null, null);
        $this->actingAs($otro)->get(route('portal.entrevista.persona', ['persona' => $persona->id]))->assertNotFound();
        $this->actingAs($otro)->delete(route('portal.entrevista.personas.destroy', $persona))->assertNotFound();

        $this->actingAs($this->cliente)->delete(route('portal.entrevista.personas.destroy', $persona))->assertRedirect(route('portal.entrevista.personas'));
        $this->assertSoftDeleted($persona);
    }

    public function test_lo_respondido_por_whatsapp_aparece_marcado_con_su_canal(): void
    {
        app(RespuestaGuardador::class)->guardar($this->entrevista(), new RespuestaData('D1', 'si'), CanalRespuesta::Whatsapp, $this->cliente);

        $this->actingAs($this->cliente)->get(route('portal.entrevista.paso', ['pantalla' => 5]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('preguntas.0.codigo', 'D1')
                ->where('preguntas.0.valor', 'si')
                ->where('preguntas.0.canal', 'whatsapp'));
    }

    public function test_los_mensajes_del_servidor_salen_en_el_idioma_elegido(): void
    {
        $ruta = route('portal.entrevista.guardar', ['pantalla' => 2, 'paso' => 'entrada']);

        $this->actingAs($this->cliente)->withUnencryptedCookie('language', 'en')
            ->put($ruta, ['respuestas' => ['B1' => 'casada']])
            ->assertSessionHasErrors(['respuestas.B1' => 'Choose one of the options.']);

        $this->actingAs($this->cliente)->withUnencryptedCookie('language', 'es')
            ->put($ruta, ['respuestas' => ['B1' => 'casada']])
            ->assertSessionHasErrors(['respuestas.B1' => 'Elige una de las opciones.']);
    }
}
