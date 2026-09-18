<?php

namespace Tests\Feature;

use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Jobs\AnalizarConversacionAgenteJob;
use App\Models\MetaAgenteReporte;
use App\Models\User;
use App\Models\WhatsappMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaAgenteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_un_administrador_puede_ver_el_panel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)->get(route('meta-agente.index'))->assertOk();
        $this->actingAs($preparador)->get(route('meta-agente.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('meta-agente.index'))->assertForbidden();
    }

    public function test_el_panel_expone_las_conversaciones_agrupadas_por_telefono_y_los_reportes_recientes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'name' => 'Jane Doe']);

        WhatsappMensaje::query()->create(['telefono' => '+15550000001', 'cliente_id' => $cliente->id, 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'a', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);
        WhatsappMensaje::query()->create(['telefono' => '+15550000001', 'cliente_id' => $cliente->id, 'rol' => RolMensajeWhatsapp::Agente, 'contenido' => 'b']);

        MetaAgenteReporte::query()->create([
            'telefono' => '+15550000001', 'cliente_id' => $cliente->id, 'rango_desde' => now(), 'rango_hasta' => now(),
            'mensajes_analizados' => 2, 'modelo' => 'x', 'hallazgos' => [['severidad' => 'alta', 'categoria' => 'grounding', 'resumen' => 'x', 'evidencia' => 'y']],
            'origen' => 'manual',
        ]);

        $response = $this->actingAs($admin)->get(route('meta-agente.index'))->assertOk();

        $conversaciones = $response->viewData('page')['props']['conversaciones'];
        $this->assertCount(1, $conversaciones);
        $this->assertSame('+15550000001', $conversaciones[0]['telefono']);
        $this->assertSame('Jane Doe', $conversaciones[0]['cliente_nombre']);
        $this->assertSame(2, $conversaciones[0]['total_mensajes']);

        $reportes = $response->viewData('page')['props']['reportes'];
        $this->assertCount(1, $reportes);
        $this->assertCount(1, $reportes[0]['hallazgos']);
    }

    public function test_store_encola_un_job_por_cada_telefono_seleccionado(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)
            ->post(route('meta-agente.store'), ['telefonos' => ['+15550000001', '+15550000002']])
            ->assertRedirect();

        Queue::assertPushed(AnalizarConversacionAgenteJob::class, 2);
    }

    public function test_un_preparador_no_puede_disparar_un_analisis(): void
    {
        Queue::fake();
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)
            ->post(route('meta-agente.store'), ['telefonos' => ['+15550000001']])
            ->assertForbidden();

        Queue::assertNotPushed(AnalizarConversacionAgenteJob::class);
    }
}
