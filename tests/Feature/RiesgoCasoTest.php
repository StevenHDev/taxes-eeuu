<?php

namespace Tests\Feature;

use App\Enums\NivelRiesgo;
use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\NivelRiesgoManual;
use App\Models\User;
use App\Services\RiesgoCasoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiesgoCasoTest extends TestCase
{
    use RefreshDatabase;

    private function servicio(): RiesgoCasoService
    {
        return app(RiesgoCasoService::class);
    }

    public function test_heuristica_da_bajo_para_un_caso_limpio_y_completo(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'completo',
        ]);
        DeterminacionFiscal::query()->create([
            'user_id' => $cliente->id, 'tax_year' => 2025, 'tipo' => 'agi',
            'resultado' => ['disponible' => true], 'version_reglas' => '2025.1', 'calculado_en' => now(),
        ]);

        $nivel = $this->servicio()->nivelAutomatico($cliente, 2025);

        $this->assertSame(NivelRiesgo::Bajo, $nivel);
    }

    public function test_heuristica_da_alto_cuando_hay_un_campo_invalido(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso',
        ]);
        CampoCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato', 'modo' => 'texto', 'valor_texto' => 'no-es-un-numero',
            'estado' => 'invalido', 'source' => 'agente_ia',
        ]);

        $nivel = $this->servicio()->nivelAutomatico($cliente, 2025);

        $this->assertSame(NivelRiesgo::Alto, $nivel);
    }

    public function test_heuristica_da_alto_cuando_hay_un_documento_invalido(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso',
        ]);
        Documento::query()->create([
            'user_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025, 'campo' => 'w2',
            'file_path' => 'x', 'file_original_name' => 'w2.exe', 'file_mime_type' => 'application/x-msdownload',
            'file_size' => 10, 'formato' => 'exe', 'estado_validacion' => 'invalido',
        ]);

        $nivel = $this->servicio()->nivelAutomatico($cliente, 2025);

        $this->assertSame(NivelRiesgo::Alto, $nivel);
    }

    public function test_heuristica_da_medio_cuando_falta_la_determinacion_fiscal(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'completo',
        ]);

        // Sin ninguna determinación fiscal calculada para el año.
        $nivel = $this->servicio()->nivelAutomatico($cliente, 2025);

        $this->assertSame(NivelRiesgo::Medio, $nivel);
    }

    public function test_override_manual_gana_sobre_el_automatico(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        // Caso que la heurística marcaría Alto (campo inválido).
        CampoCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato', 'modo' => 'texto', 'valor_texto' => 'x',
            'estado' => 'invalido', 'source' => 'agente_ia',
        ]);
        NivelRiesgoManual::query()->create([
            'user_id' => $cliente->id, 'tax_year' => 2025, 'nivel' => NivelRiesgo::Bajo,
            'establecido_por' => $admin->id, 'establecido_en' => now(),
        ]);

        $efectivo = $this->servicio()->nivelEfectivo($cliente, 2025);

        $this->assertSame(NivelRiesgo::Bajo, $efectivo['nivel']);
        $this->assertSame('manual', $efectivo['fuente']);
    }

    public function test_establecer_y_limpiar_el_override_via_web(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)
            ->post(route('clientes.nivel-riesgo.store', $cliente), ['tax_year' => 2025, 'nivel' => 'alto'])
            ->assertRedirect();

        $this->assertDatabaseHas('niveles_riesgo_manual', [
            'user_id' => $cliente->id, 'tax_year' => 2025, 'nivel' => 'alto', 'establecido_por' => $admin->id,
        ]);

        $efectivo = $this->servicio()->nivelEfectivo($cliente, 2025);
        $this->assertSame(NivelRiesgo::Alto, $efectivo['nivel']);
        $this->assertSame('manual', $efectivo['fuente']);

        $this->actingAs($admin)
            ->delete(route('clientes.nivel-riesgo.destroy', $cliente), ['tax_year' => 2025])
            ->assertRedirect();

        $this->assertDatabaseCount('niveles_riesgo_manual', 0);

        $efectivoTrasLimpiar = $this->servicio()->nivelEfectivo($cliente, 2025);
        $this->assertSame('automatico', $efectivoTrasLimpiar['fuente']);
    }

    public function test_un_preparador_no_puede_fijar_el_riesgo_de_un_cliente_que_no_tiene_asignado(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $otroPreparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $otroPreparador->id]);

        $this->actingAs($preparador)
            ->post(route('clientes.nivel-riesgo.store', $cliente), ['tax_year' => 2025, 'nivel' => 'alto'])
            ->assertForbidden();
    }

    public function test_el_dashboard_cuenta_los_casos_de_alto_riesgo_visibles(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $propio = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $ajeno = User::factory()->create(['role' => UserRole::Client]);

        NivelRiesgoManual::query()->create([
            'user_id' => $propio->id, 'tax_year' => 2025, 'nivel' => NivelRiesgo::Alto,
            'establecido_por' => $preparador->id, 'establecido_en' => now(),
        ]);
        NivelRiesgoManual::query()->create([
            'user_id' => $ajeno->id, 'tax_year' => 2025, 'nivel' => NivelRiesgo::Alto,
            'establecido_por' => $preparador->id, 'establecido_en' => now(),
        ]);

        $this->actingAs($preparador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('resumen.casos_alto_riesgo', 1));
    }
}
