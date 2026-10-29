<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F0.1: el personal solo usa la plataforma con 2FA confirmado o una passkey.
 */
class EnsureStaffHasMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa_roles' => ['preparer', 'administrator']]);
    }

    private function conPasskey(User $user): User
    {
        DB::table('passkeys')->insert([
            'user_id' => $user->id,
            'name' => 'Llave de prueba',
            'credential_id' => 'cred-'.$user->id,
            'credential' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_un_preparador_sin_mfa_es_enviado_a_seguridad(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('dashboard'))->assertRedirect(route('security.edit'));
        $this->actingAs($preparador)->get(route('clientes.index'))->assertRedirect(route('security.edit'));
    }

    public function test_un_administrador_sin_mfa_tampoco_puede_escribir(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)
            ->patch(route('usuarios.prueba', $cliente), ['es_prueba' => true])
            ->assertRedirect(route('security.edit'));

        $this->assertFalse($cliente->fresh()->es_prueba);
    }

    public function test_una_peticion_json_sin_mfa_recibe_403(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->getJson(route('clientes.index'))->assertForbidden();
    }

    public function test_un_preparador_con_2fa_confirmado_entra(): void
    {
        $preparador = User::factory()->withTwoFactor()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('dashboard'))->assertOk();
    }

    public function test_un_preparador_con_2fa_sin_confirmar_no_entra(): void
    {
        $preparador = User::factory()->withTwoFactor()->create([
            'role' => UserRole::Preparer,
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($preparador)->get(route('dashboard'))->assertRedirect(route('security.edit'));
    }

    public function test_un_administrador_con_passkey_entra(): void
    {
        $admin = $this->conPasskey(User::factory()->create(['role' => UserRole::Administrator]));

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_un_cliente_sin_mfa_entra_al_portal(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->get(route('dashboard'))->assertOk();
    }

    public function test_las_rutas_para_configurar_el_mfa_siguen_libres(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('auth.debeConfigurarMfa', true));

        $this->actingAs($preparador)->get(route('profile.edit'))->assertOk();
        $this->actingAs($preparador)->get(route('password.confirm'))->assertOk();
        $this->actingAs($preparador)->post(route('two-factor.enable'))->assertSessionHasNoErrors();
        $this->actingAs($preparador)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_con_mfa_el_aviso_no_aparece(): void
    {
        $preparador = User::factory()->withTwoFactor()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.debeConfigurarMfa', false));
    }

    public function test_sin_roles_configurados_no_se_exige(): void
    {
        config(['security.mfa_roles' => []]);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('dashboard'))->assertOk();
    }
}
