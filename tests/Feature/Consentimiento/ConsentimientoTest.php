<?php

namespace Tests\Feature\Consentimiento;

use App\Enums\UserRole;
use App\Models\Consentimiento\Consentimiento;
use App\Models\Consentimiento\ConsentimientoTexto;
use App\Models\User;
use App\Services\Consentimiento\ConsentimientosCargador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * F2.4: consentimiento §7216 en el portal para los clientes con la
 * entrevista nueva.
 */
class ConsentimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsentimientosCargador::class)->cargar();
        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true, 'name' => 'Ana Prueba']);
        $this->cliente->forceFill(['entrevista_catalogo' => true])->save();
    }

    private function firmar(array $datos = []): TestResponse
    {
        return $this->actingAs($this->cliente)->post(route('portal.consentimiento.store'), [
            'acepto' => true, 'nombre_escrito' => 'Ana Prueba', 'version' => 1, ...$datos,
        ]);
    }

    public function test_sin_firma_los_datos_del_portal_llevan_al_consentimiento(): void
    {
        $this->actingAs($this->cliente)->get(route('portal.formulario'))->assertRedirect(route('portal.consentimiento'));
        $this->actingAs($this->cliente)->get(route('portal.chat'))->assertOk();

        $this->actingAs($this->cliente)->get(route('portal.consentimiento'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/consentimiento')
                ->where('texto.version', 1)
                ->where('texto.provisional', true)
                ->where('taxYear', config('tax.current_tax_year')));
    }

    public function test_el_flujo_actual_no_pide_consentimiento(): void
    {
        $otro = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($otro)->get(route('portal.formulario'))->assertRedirect(route('portal.chat'));
        $this->actingAs($otro)->get(route('portal.consentimiento'))->assertForbidden();
    }

    public function test_la_firma_guarda_texto_version_ip_y_hora_y_deja_entrar(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeader('User-Agent', 'Prueba/1.0');

        $this->firmar()->assertRedirect(route('portal.entrevista.inicio'));

        $firma = Consentimiento::query()->sole();
        $this->assertSame('Ana Prueba', $firma->nombre_escrito);
        $this->assertSame(1, $firma->texto->version);
        $this->assertSame('203.0.113.7', $firma->ip);
        $this->assertSame('Prueba/1.0', $firma->user_agent);
        $this->assertNotNull($firma->aceptado_en);

        $this->actingAs($this->cliente)->get(route('portal.entrevista.inicio'))->assertOk();
        $this->actingAs($this->cliente)->get(route('portal.consentimiento'))->assertRedirect(route('portal.entrevista.inicio'));
    }

    public function test_sin_casilla_o_nombre_no_firma(): void
    {
        $this->firmar(['acepto' => false])->assertSessionHasErrors('acepto');
        $this->firmar(['nombre_escrito' => ''])->assertSessionHasErrors('nombre_escrito');

        $this->assertSame(0, Consentimiento::query()->count());
    }

    public function test_un_texto_nuevo_vuelve_a_pedir_la_firma_y_una_version_vieja_no_se_firma(): void
    {
        $this->firmar();
        ConsentimientoTexto::query()->create([
            'codigo' => 'uso_7216', 'version' => 2, 'titulo_es' => 'Consentimiento', 'texto_es' => 'Texto nuevo',
            'provisional' => true, 'vigente_desde' => now()->toDateString(),
        ]);

        $this->actingAs($this->cliente)->get(route('portal.formulario'))->assertRedirect(route('portal.consentimiento'));
        $this->firmar(['version' => 1])->assertSessionHasErrors('version');
        $this->firmar(['version' => 2])->assertRedirect(route('portal.entrevista.inicio'));
    }

    public function test_una_firma_no_se_edita_ni_tiene_rutas_para_editar_o_borrar(): void
    {
        $this->firmar();

        $this->expectException(LogicException::class);
        Consentimiento::query()->sole()->update(['nombre_escrito' => 'Otro']);
    }

    public function test_solo_hay_rutas_para_ver_y_firmar(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'consentimiento'));

        $this->assertEqualsCanonicalizing(['GET', 'HEAD', 'POST'], $rutas->flatMap(fn ($r) => $r->methods())->unique()->values()->all());
    }

    public function test_con_texto_provisional_un_cliente_real_no_pasa_a_la_entrevista_nueva(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $real = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)->patch(route('usuarios.entrevista-catalogo', $real), ['entrevista_catalogo' => true])
            ->assertSessionHasErrors('entrevista_catalogo');
        $this->assertFalse($real->fresh()->entrevista_catalogo);

        // Y aunque se le hubiera activado por otro camino, no puede firmar.
        $real->forceFill(['entrevista_catalogo' => true])->save();
        $this->actingAs($real)->post(route('portal.consentimiento.store'), ['acepto' => true, 'nombre_escrito' => 'Real', 'version' => 1])->assertForbidden();
    }

    public function test_la_carga_es_idempotente_y_no_cambia_un_texto_ya_firmado(): void
    {
        $this->assertSame(['uso_7216-v1'], app(ConsentimientosCargador::class)->cargar()['sin_cambios']);

        $this->firmar();
        ConsentimientoTexto::query()->where('version', 1)->update(['texto_es' => 'editado a mano']);

        $this->expectException(InvalidArgumentException::class);
        app(ConsentimientosCargador::class)->cargar();
    }
}
