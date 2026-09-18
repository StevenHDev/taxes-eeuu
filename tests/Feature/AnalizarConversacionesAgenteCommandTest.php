<?php

namespace Tests\Feature;

use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\FormaCliente;
use App\Models\MetaAgenteReporte;
use App\Models\User;
use App\Models\WhatsappMensaje;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalizarConversacionesAgenteCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'test-key', 'meta_agente.modelo' => 'test-model']);
        $this->seed(AgentePromptsSeeder::class);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['hallazgos' => []])]]],
            ]),
        ]);
    }

    public function test_con_telefono_analiza_solo_las_conversaciones_indicadas(): void
    {
        WhatsappMensaje::query()->create(['telefono' => '+15550000001', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'a', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);
        WhatsappMensaje::query()->create(['telefono' => '+15550000002', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'b', 'mensaje_externo_id' => 'SM'.str_repeat('b', 32)]);

        $this->artisan('meta-agente:analizar', ['--telefono' => ['+15550000001']])->assertExitCode(0);

        $this->assertDatabaseCount('meta_agente_reportes', 1);
        $this->assertDatabaseHas('meta_agente_reportes', ['telefono' => '+15550000001', 'origen' => 'manual']);
    }

    public function test_sin_telefono_analiza_todas_las_conversaciones_con_actividad_nueva(): void
    {
        config(['meta_agente.alcance_diario' => 'actividad']);
        WhatsappMensaje::query()->create(['telefono' => '+15550000001', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'a', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);
        WhatsappMensaje::query()->create(['telefono' => '+15550000002', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'b', 'mensaje_externo_id' => 'SM'.str_repeat('b', 32)]);

        $this->artisan('meta-agente:analizar')->assertExitCode(0);

        $this->assertDatabaseCount('meta_agente_reportes', 2);
        $this->assertDatabaseHas('meta_agente_reportes', ['telefono' => '+15550000001', 'origen' => 'programado']);
        $this->assertDatabaseHas('meta_agente_reportes', ['telefono' => '+15550000002', 'origen' => 'programado']);
    }

    public function test_sin_telefono_no_repite_una_conversacion_sin_actividad_nueva_desde_su_ultimo_reporte(): void
    {
        $mensaje = WhatsappMensaje::query()->create(['telefono' => '+15550000001', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'a', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);

        MetaAgenteReporte::query()->create([
            'telefono' => '+15550000001', 'rango_desde' => $mensaje->created_at, 'rango_hasta' => $mensaje->created_at,
            'mensajes_analizados' => 1, 'modelo' => 'x', 'hallazgos' => [], 'origen' => 'programado',
        ]);

        $this->artisan('meta-agente:analizar')->assertExitCode(0);

        // El único reporte sigue siendo el que ya existía — no se generó uno nuevo.
        $this->assertDatabaseCount('meta_agente_reportes', 1);
        Http::assertNothingSent();
    }

    public function test_alcance_cierre_solo_analiza_conversaciones_cuyo_cliente_ya_llego_a_cierre(): void
    {
        config(['meta_agente.alcance_diario' => 'cierre']);

        $clienteEnRecoleccion = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $clienteEnRecoleccion->id, 'forma' => 'form_990', 'tax_year' => 2025, 'estado' => 'en_progreso']);
        WhatsappMensaje::query()->create(['telefono' => '+15550000001', 'cliente_id' => $clienteEnRecoleccion->id, 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'a', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);

        // Sin cuenta asociada: nunca puede estar en Cierre.
        WhatsappMensaje::query()->create(['telefono' => '+15550000002', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'b', 'mensaje_externo_id' => 'SM'.str_repeat('b', 32)]);

        $this->artisan('meta-agente:analizar')->assertExitCode(0);

        $this->assertDatabaseCount('meta_agente_reportes', 0);
    }
}
