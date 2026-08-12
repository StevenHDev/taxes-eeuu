<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BitacoraActividadTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_un_administrador_ve_la_bitacora(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)->get(route('bitacora.index'))->assertOk();
        $this->actingAs($preparador)->get(route('bitacora.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('bitacora.index'))->assertForbidden();
    }

    public function test_crear_un_usuario_genera_un_evento_creado(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Preparador',
            'email' => 'nuevo-preparador@example.com',
            'password' => 'password123',
            'role' => 'preparer',
        ])->assertRedirect();

        $creado = User::query()->where('email', 'nuevo-preparador@example.com')->firstOrFail();

        $this->assertDatabaseHas('bitacora_actividad', [
            'accion' => AccionAuditoria::Creado->value,
            'auditable_type' => User::class,
            'auditable_id' => $creado->id,
            'actor_id' => $admin->id,
        ]);
    }

    /**
     * El nombre del atributo cambiado sí se guarda (para saber "qué pasó"),
     * pero el valor real nunca — eso sigue siendo trabajo exclusivo de
     * HistorialCambio (ver la migración de bitacora_actividad).
     */
    public function test_actualizar_un_usuario_registra_el_campo_pero_nunca_el_valor(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => '3000000000']);

        $this->actingAs($admin)->patch(route('usuarios.update', $cliente), [
            'name' => $cliente->name,
            'email' => $cliente->email,
            'phone' => '3111111111',
            'role' => 'client',
        ])->assertRedirect();

        $evento = BitacoraActividad::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $cliente->id)
            ->where('accion', AccionAuditoria::Actualizado)
            ->firstOrFail();

        $this->assertSame(['phone'], $evento->campos_afectados);
        $this->assertStringNotContainsString('3111111111', json_encode($evento->toArray()));
        $this->assertStringNotContainsString('3000000000', json_encode($evento->toArray()));
    }

    public function test_eliminar_un_usuario_genera_un_evento_eliminado(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $clienteId = $cliente->id;

        $this->actingAs($admin)->delete(route('usuarios.destroy', $cliente))->assertRedirect();

        $this->assertDatabaseHas('bitacora_actividad', [
            'accion' => AccionAuditoria::Eliminado->value,
            'auditable_type' => User::class,
            'auditable_id' => $clienteId,
        ]);
    }

    public function test_subir_un_documento_genera_un_evento_creado_sin_exponer_datos(): void
    {
        Storage::fake('local');
        $agente = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($agente, [ApiAbility::EventosWrite->value]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
        ])->assertCreated();

        $this->assertDatabaseHas('bitacora_actividad', [
            'accion' => AccionAuditoria::Creado->value,
            'actor_id' => $agente->id,
        ]);
    }

    public function test_iniciar_sesion_genera_un_evento_de_inicio_de_sesion(): void
    {
        $usuario = User::factory()->create(['role' => UserRole::Client]);

        $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('bitacora_actividad', [
            'accion' => AccionAuditoria::InicioSesion->value,
            'auditable_type' => User::class,
            'auditable_id' => $usuario->id,
        ]);
    }

    public function test_cerrar_sesion_genera_un_evento_de_cierre_de_sesion(): void
    {
        $usuario = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($usuario)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertDatabaseHas('bitacora_actividad', [
            'accion' => AccionAuditoria::CierreSesion->value,
            'auditable_type' => User::class,
            'auditable_id' => $usuario->id,
        ]);
    }
}
