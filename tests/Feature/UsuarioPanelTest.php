<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\Documento;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsuarioPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_un_administrador_accede_al_panel_de_usuarios(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk();
        $this->actingAs($preparador)->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_un_administrador_puede_crear_un_usuario_con_cualquier_rol(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Preparador',
            'email' => 'nuevo-preparador@example.com',
            'password' => 'password123',
            'role' => 'preparer',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'nuevo-preparador@example.com', 'role' => 'preparer']);
    }

    public function test_un_preparador_no_puede_crear_usuarios_desde_el_panel_de_administracion(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->post(route('usuarios.store'), [
            'name' => 'Intento',
            'email' => 'intento@example.com',
            'password' => 'password123',
            'role' => 'administrator',
        ])->assertForbidden();
    }

    public function test_un_administrador_puede_reasignar_el_preparador_de_un_cliente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparadorViejo = User::factory()->create(['role' => UserRole::Preparer]);
        $preparadorNuevo = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparadorViejo->id]);

        $this->actingAs($admin)->patch(route('usuarios.update', $cliente), [
            'name' => $cliente->name,
            'email' => $cliente->email,
            'role' => 'client',
            'preparer_id' => $preparadorNuevo->id,
        ])->assertRedirect();

        $this->assertSame($preparadorNuevo->id, $cliente->fresh()->preparer_id);
    }

    public function test_un_preparador_no_puede_editar_el_perfil_de_su_propio_cliente(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)->patch(route('usuarios.update', $cliente), [
            'name' => $cliente->name,
            'email' => $cliente->email,
            'role' => 'administrator',
        ])->assertForbidden();

        $this->assertSame(UserRole::Client, $cliente->fresh()->role);
    }

    public function test_un_administrador_no_puede_eliminarse_a_si_mismo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->delete(route('usuarios.destroy', $admin))->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_un_administrador_archiva_un_cliente_y_conserva_sus_datos(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'form_1040',
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => 1000,
            'estado' => 'recibido',
            'source' => 'agente_ia',
        ]);

        $this->actingAs($admin)->delete(route('usuarios.destroy', $cliente))->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $cliente->id]);
        $this->assertDatabaseHas('campos_cliente', ['user_id' => $cliente->id]);

        $this->actingAs($admin)->patch(route('usuarios.restore', $cliente->id))->assertRedirect();
        $this->assertNotSoftDeleted('users', ['id' => $cliente->id]);
    }

    public function test_un_usuario_que_no_es_de_prueba_no_se_puede_eliminar_definitivamente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)->delete(route('usuarios.force-destroy', $cliente))->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $cliente->id]);
    }

    public function test_un_usuario_de_prueba_se_elimina_definitivamente_con_archivos_y_conversacion(): void
    {
        Storage::fake(Documento::disco());
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15551234567']);
        $documento = Documento::query()->create([
            'user_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025, 'campo' => 'w2',
            'file_path' => 'documentos/w2.pdf', 'file_original_name' => 'w2.pdf', 'file_mime_type' => 'application/pdf',
            'file_size' => 10, 'formato' => 'pdf', 'estado_validacion' => 'recibido',
        ]);
        Storage::disk(Documento::disco())->put('documentos/w2.pdf', 'contenido');
        $documento->delete(); // un documento ya archivado también se borra
        WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => 'cliente', 'contenido' => 'hola', 'cliente_id' => $cliente->id]);
        WhatsappControl::query()->create(['telefono' => '+15551234567', 'estado' => 'agente', 'cliente_id' => $cliente->id]);

        $this->actingAs($admin)->patch(route('usuarios.prueba', $cliente), ['es_prueba' => true])->assertRedirect();
        $this->actingAs($admin)
            ->delete(route('usuarios.force-destroy', $cliente), ['eliminar_conversacion_whatsapp' => true])
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseMissing('users', ['id' => $cliente->id]);
        $this->assertDatabaseMissing('documentos', ['id' => $documento->id]);
        Storage::disk(Documento::disco())->assertMissing('documentos/w2.pdf');
        $this->assertDatabaseMissing('whatsapp_mensajes', ['telefono' => '+15551234567']);
        $this->assertDatabaseMissing('whatsapp_control', ['telefono' => '+15551234567']);
    }

    public function test_eliminar_definitivamente_sin_la_opcion_conserva_la_conversacion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15551234567', 'es_prueba' => true]);
        WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => 'cliente', 'contenido' => 'hola', 'cliente_id' => $cliente->id]);

        $this->actingAs($admin)->delete(route('usuarios.force-destroy', $cliente))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $cliente->id]);
        $this->assertDatabaseHas('whatsapp_mensajes', ['telefono' => '+15551234567']);
    }

    public function test_solo_un_administrador_marca_usuarios_de_prueba(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)->patch(route('usuarios.prueba', $cliente), ['es_prueba' => true])->assertForbidden();
        $this->assertFalse($cliente->fresh()->es_prueba);
    }

    public function test_el_listado_de_usuarios_incluye_los_archivados(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        $cliente->delete();

        $this->actingAs($admin)
            ->get(route('usuarios.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('usuarios', fn ($usuarios) => collect($usuarios)->contains(fn ($u) => $u['id'] === $cliente->id && $u['archivado'] && $u['es_prueba'])));
    }
}
