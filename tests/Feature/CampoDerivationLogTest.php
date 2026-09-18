<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\CampoDerivationLog;
use App\Models\User;
use Database\Seeders\RelacionesDocumentoCampoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ver database/migrations/2026_09_17_140000_create_campo_derivation_logs_table.php
 * y EventoRecoleccionService::registrarDerivacion() — análogo a
 * agent_derivation_logs del agente de salud (Natalia), para depurar "por qué
 * no se guardó tal campo desde tal documento" sin releer el historial de la
 * conversación a mano.
 */
class CampoDerivationLogTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAgente(): User
    {
        $agente = User::factory()->create(['role' => UserRole::Administrator, 'name' => 'Agente conversacional']);

        Sanctum::actingAs($agente, [ApiAbility::EventosWrite->value]);

        return $agente;
    }

    /**
     * w2 tiene 4 relaciones declaradas hoy: ingresos.salarios,
     * impuestos_retenidos, gastos_cuidado_dependientes.monto_anual,
     * salarios_medicare. Si el agente manda las 4 en `revelados`, no debe
     * quedar ninguna faltante.
     */
    public function test_documento_con_todas_las_relaciones_cubiertas_no_deja_faltantes(): void
    {
        Storage::fake('local');
        $this->seed(RelacionesDocumentoCampoSeeder::class);
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
            'revelados' => [
                ['forma' => 'form_1040', 'campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'subcampo' => 'salarios', 'contenido' => ['salarios' => 55665]],
                ['forma' => 'form_1040', 'campo' => 'impuestos_retenidos', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 3291.79],
                ['forma' => 'form_1040', 'campo' => 'gastos_cuidado_dependientes', 'tipo_campo' => 'mixto', 'tipo_dato' => 'object', 'subcampo' => 'monto_anual', 'contenido' => ['monto_anual' => 0]],
                ['forma' => 'form_1040', 'campo' => 'salarios_medicare', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 55665],
            ],
        ])->assertCreated();

        $log = CampoDerivationLog::query()->where('user_id', $cliente->id)->first();

        $this->assertNotNull($log);
        $this->assertSame('w2', $log->documento_campo);
        $this->assertCount(4, $log->relaciones_esperadas);
        $this->assertCount(4, $log->revelados_recibidos);
        $this->assertSame([], $log->relaciones_faltantes);
        $this->assertFalse($log->tieneFaltantes());
    }

    /**
     * Caso real que motivó esta feature: el agente sube el w2 pero solo
     * manda 2 de las 4 relaciones en `revelados` — las otras 2 quedan
     * registradas como faltantes, visibles sin tener que releer la
     * conversación completa.
     */
    public function test_documento_con_relaciones_sin_cubrir_las_deja_registradas_como_faltantes(): void
    {
        Storage::fake('local');
        $this->seed(RelacionesDocumentoCampoSeeder::class);
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
            'revelados' => [
                ['forma' => 'form_1040', 'campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'subcampo' => 'salarios', 'contenido' => ['salarios' => 55665]],
                ['forma' => 'form_1040', 'campo' => 'impuestos_retenidos', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 3291.79],
            ],
        ])->assertCreated();

        $log = CampoDerivationLog::query()->where('user_id', $cliente->id)->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->tieneFaltantes());
        $this->assertCount(2, $log->relaciones_faltantes);
        $this->assertEqualsCanonicalizing(
            ['gastos_cuidado_dependientes', 'salarios_medicare'],
            collect($log->relaciones_faltantes)->pluck('campo')->all(),
        );
    }

    public function test_un_documento_sin_relaciones_declaradas_no_genera_log(): void
    {
        Storage::fake('local');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // form_1098_t es el único campo que queda bajo documentos_extra desde
        // la Fase 2 del plan de cierre de brecha GTS (declaracion_anio_anterior
        // se promovió a transversal — ver CatalogoCamposSeeder). El seeder de
        // relaciones no corre acá (solo catálogo/parámetros, ver
        // Tests\TestCase), así que no hay `revela` declarado en este test.
        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'documentos_extra',
            'tax_year' => 2025,
            'campo' => 'form_1098_t',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('1098t.pdf', 10),
        ])->assertCreated();

        $this->assertSame(0, CampoDerivationLog::query()->count());
    }

    public function test_un_campo_de_texto_sin_documento_no_genera_log(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 500,
        ])->assertCreated();

        $this->assertSame(0, CampoDerivationLog::query()->count());
    }
}
