<?php

namespace Tests\Feature\Consentimiento;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Consentimiento\EnlaceActivacion;
use App\Services\WhatsappAgent\ToolExecutor;
use App\Support\AgenteWhatsappUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * F2.4: el primer enlace que manda el agente por WhatsApp crea la
 * contraseña, deja la sesión iniciada y lleva al consentimiento.
 */
class ActivacionCuentaTest extends TestCase
{
    use RefreshDatabase;

    private const TELEFONO = '+15550001111';

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsentimientosCargador::class)->cargar();
        $this->cliente = User::factory()->create([
            'role' => UserRole::Client, 'phone' => self::TELEFONO, 'es_prueba' => true,
        ]);
        $this->cliente->forceFill(['entrevista_catalogo' => true, 'contrasena_definida_en' => null])->save();
    }

    private function enlace(): string
    {
        return app(EnlaceActivacion::class)->para($this->cliente->fresh());
    }

    public function test_el_enlace_crea_la_contrasena_inicia_sesion_y_lleva_al_consentimiento(): void
    {
        $enlace = $this->enlace();

        $this->get($enlace)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/activar-cuenta')
                ->where('vigente', true)
                ->missing('email'));

        $this->post($enlace, ['password' => 'Nueva-clave-segura-2025', 'password_confirmation' => 'Nueva-clave-segura-2025'])
            ->assertRedirect(route('portal.consentimiento'));

        $this->assertAuthenticatedAs($this->cliente);
        $cliente = $this->cliente->fresh();
        $this->assertNotNull($cliente->contrasena_definida_en);
        $this->assertTrue(Hash::check('Nueva-clave-segura-2025', $cliente->password));
    }

    public function test_el_enlace_es_de_un_solo_uso(): void
    {
        $enlace = $this->enlace();
        $this->post($enlace, ['password' => 'Nueva-clave-segura-2025', 'password_confirmation' => 'Nueva-clave-segura-2025']);
        auth()->logout();
        $clave = $this->cliente->fresh()->password;

        $this->post($enlace, ['password' => 'Otra-clave-segura-2025', 'password_confirmation' => 'Otra-clave-segura-2025'])
            ->assertSessionHasErrors('enlace');
        $this->get($enlace)->assertInertia(fn (AssertableInertia $page) => $page->where('vigente', false));

        $this->assertSame($clave, $this->cliente->fresh()->password);
        $this->assertGuest();
    }

    public function test_un_enlace_vencido_o_alterado_no_sirve(): void
    {
        $enlace = $this->enlace();

        $this->get(str_replace('signature=', 'signature=x', $enlace))->assertForbidden();
        $this->get(route('activacion.show', $this->cliente))->assertForbidden();

        $this->travel(EnlaceActivacion::MINUTOS + 1)->minutes();
        $this->get($enlace)->assertForbidden();
        $this->post($enlace, ['password' => 'Nueva-clave-segura-2025', 'password_confirmation' => 'Nueva-clave-segura-2025'])->assertForbidden();
    }

    public function test_sin_la_entrevista_nueva_o_con_contrasena_no_hay_enlace(): void
    {
        $this->cliente->forceFill(['entrevista_catalogo' => false])->save();
        $this->assertNull(app(EnlaceActivacion::class)->para($this->cliente));

        $this->cliente->forceFill(['entrevista_catalogo' => true, 'contrasena_definida_en' => now()])->save();
        $this->assertNull(app(EnlaceActivacion::class)->para($this->cliente));
    }

    public function test_restablecer_la_contrasena_por_correo_la_marca_como_definida(): void
    {
        $token = Password::createToken($this->cliente);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $this->cliente->email,
            'password' => 'Nueva-clave-segura-2025', 'password_confirmation' => 'Nueva-clave-segura-2025',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($this->cliente->fresh()->contrasena_definida_en);
        $this->assertNull(app(EnlaceActivacion::class)->para($this->cliente->fresh()));
    }

    public function test_la_tool_solo_manda_enlaces_al_whatsapp_de_la_cuenta_y_sin_contrasena(): void
    {
        $tools = app(ToolExecutor::class);
        $actor = AgenteWhatsappUser::resolver();

        $otro = $tools->ejecutar('enviar_enlace_activacion', [], $this->cliente, $actor, telefono: '+15559998888');
        $this->assertFalse($otro['enviado']);

        $ok = $tools->ejecutar('enviar_enlace_activacion', [], $this->cliente, $actor, telefono: self::TELEFONO);
        $this->assertTrue($ok['enviado']);
        $this->assertStringStartsWith(EnlaceActivacion::prefijo(), $ok['enlace']);

        $this->cliente->forceFill(['contrasena_definida_en' => now()])->save();
        $definida = $tools->ejecutar('enviar_enlace_activacion', [], $this->cliente->fresh(), $actor, telefono: self::TELEFONO);
        $this->assertFalse($definida['enviado']);
        $this->assertStringContainsString('¿Olvidaste tu contraseña?', $definida['motivo']);
        $this->assertArrayNotHasKey('enlace', $definida);
    }

    public function test_las_cuentas_creadas_con_contrasena_ya_la_tienen_definida(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Preparadora', 'email' => 'prep@example.com', 'role' => UserRole::Preparer->value,
            'password' => 'Clave-segura-2025', 'password_confirmation' => 'Clave-segura-2025',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(User::query()->where('email', 'prep@example.com')->value('contrasena_definida_en'));
    }
}
