<?php

namespace Tests\Feature;

use App\Enums\OrigenAnalisisMetaAgente;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\FormaCliente;
use App\Models\MetaAgenteReporte;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Services\MetaAgente\ConversacionAnalizadorService;
use Database\Seeders\AgentePromptsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversacionAnalizadorServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConversacionAnalizadorService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'test-key', 'meta_agente.modelo' => 'test-model']);
        $this->seed(AgentePromptsSeeder::class);

        $this->servicio = app(ConversacionAnalizadorService::class);
    }

    private function respuestaOpenAi(array $hallazgos): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['hallazgos' => $hallazgos])]]]];
    }

    public function test_sin_mensajes_devuelve_null_y_no_llama_a_openai(): void
    {
        $resultado = $this->servicio->analizar('+15550000000', OrigenAnalisisMetaAgente::Manual);

        $this->assertNull($resultado);
        Http::assertNothingSent();
    }

    public function test_guarda_el_reporte_con_los_hallazgos_devueltos(): void
    {
        WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'Hola', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);
        WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => RolMensajeWhatsapp::Agente, 'contenido' => '¿Ya tienes cuenta?']);

        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi([
                ['severidad' => 'media', 'categoria' => 'orden', 'resumen' => 'Se repitió una pregunta.', 'evidencia' => 'Turno 3 y 5.'],
            ])),
        ]);

        $reporte = $this->servicio->analizar('+15551234567', OrigenAnalisisMetaAgente::Manual);

        $this->assertNotNull($reporte);
        $this->assertSame('+15551234567', $reporte->telefono);
        $this->assertSame(2, $reporte->mensajes_analizados);
        $this->assertCount(1, $reporte->hallazgos);
        $this->assertSame('media', $reporte->hallazgos[0]['severidad']);
        $this->assertSame(OrigenAnalisisMetaAgente::Manual, $reporte->origen);
        $this->assertDatabaseCount('meta_agente_reportes', 1);
    }

    public function test_sin_hallazgos_guarda_un_reporte_con_arreglo_vacio(): void
    {
        WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'Hola', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);

        Http::fake(['api.openai.com/*' => Http::response($this->respuestaOpenAi([]))]);

        $reporte = $this->servicio->analizar('+15551234567', OrigenAnalisisMetaAgente::Programado);

        $this->assertSame([], $reporte->hallazgos);
    }

    public function test_una_respuesta_no_json_queda_registrada_como_hallazgo_de_error_en_vez_de_perderse(): void
    {
        WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'Hola', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);

        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'esto no es JSON']]]]),
        ]);

        $reporte = $this->servicio->analizar('+15551234567', OrigenAnalisisMetaAgente::Manual);

        $this->assertCount(1, $reporte->hallazgos);
        $this->assertSame('error-interno', $reporte->hallazgos[0]['categoria']);
    }

    /**
     * El listado de "estado actual de los campos" (a diferencia de la
     * transcripción cruda, que preserva lo que el cliente escribió tal
     * cual, igual que ve el propio agente conversacional) SIEMPRE debe usar
     * el valor enmascarado de un campo sensible — nunca CampoCliente::valor_texto
     * directo.
     */
    public function test_los_campos_sensibles_del_cliente_van_enmascarados_al_prompt(): void
    {
        // SSN distinto del que aparece como ejemplo de formato dentro del
        // propio prompt (recoleccion.md usa "123-45-6789" como muestra) —
        // usar ese mismo número produciría un falso positivo: el crudo
        // aparecería en el prompt de todos modos, sin relación con el
        // enmascarado de este cliente.
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);
        CampoCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025, 'campo' => 'identificacion_ssn_itin',
            'tipo_campo' => 'dato', 'modo' => 'texto', 'valor_texto' => '987-65-4321', 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);
        // El mensaje del cliente NO menciona el SSN — así se aísla que el
        // enmascarado viene del listado de campos, no de la transcripción
        // (que sí preserva texto crudo del cliente, igual que el agente
        // principal, y no es lo que este test verifica).
        WhatsappMensaje::query()->create([
            'telefono' => '+15551234567', 'cliente_id' => $cliente->id, 'rol' => RolMensajeWhatsapp::Cliente,
            'contenido' => 'Hola, ya te compartí mis datos.', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32),
        ]);

        Http::fake(['api.openai.com/*' => Http::response($this->respuestaOpenAi([]))]);

        $this->servicio->analizar('+15551234567', OrigenAnalisisMetaAgente::Manual);

        Http::assertSent(function ($request) {
            $contenido = collect($request['messages'])->pluck('content')->implode(' ');

            return ! str_contains($contenido, '987-65-4321') && str_contains($contenido, '4321');
        });
    }

    public function test_desde_solo_analiza_mensajes_posteriores_a_esa_fecha(): void
    {
        // created_at no está en el Fillable del modelo (nunca se asigna a
        // mano en producción) — se fuerza después de crear, no vía create().
        $viejo = WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'mensaje viejo', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);
        $viejo->forceFill(['created_at' => now()->subDays(2)])->save();
        $nuevo = WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'mensaje nuevo', 'mensaje_externo_id' => 'SM'.str_repeat('b', 32)]);
        $nuevo->forceFill(['created_at' => now()])->save();

        Http::fake(['api.openai.com/*' => Http::response($this->respuestaOpenAi([]))]);

        $reporte = $this->servicio->analizar('+15551234567', OrigenAnalisisMetaAgente::Programado, desde: $viejo->created_at);

        $this->assertSame(1, $reporte->mensajes_analizados);
        Http::assertSent(fn ($request) => str_contains(collect($request['messages'])->pluck('content')->implode(' '), 'mensaje nuevo')
            && ! str_contains(collect($request['messages'])->pluck('content')->implode(' '), 'mensaje viejo'));
    }

    public function test_un_reporte_previo_no_impide_re_analizar_manualmente_toda_la_conversacion(): void
    {
        WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => RolMensajeWhatsapp::Cliente, 'contenido' => 'Hola', 'mensaje_externo_id' => 'SM'.str_repeat('a', 32)]);
        MetaAgenteReporte::query()->create([
            'telefono' => '+15551234567', 'rango_desde' => now(), 'rango_hasta' => now(),
            'mensajes_analizados' => 1, 'modelo' => 'x', 'hallazgos' => [], 'origen' => 'programado',
        ]);

        Http::fake(['api.openai.com/*' => Http::response($this->respuestaOpenAi([]))]);

        $reporte = $this->servicio->analizar('+15551234567', OrigenAnalisisMetaAgente::Manual);

        $this->assertSame(1, $reporte->mensajes_analizados);
    }
}
