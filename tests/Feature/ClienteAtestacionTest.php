<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Services\AgenteToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 4 del plan de cierre de brecha GTS (atestación final de cierre) — ver
 * ClienteAtestacion y App\Services\AgenteToolService::atestacionVigente().
 * "¿Sigue vigente?" se resuelve comparando la atestación más reciente contra
 * el último cambio en historial_cambios, sin una columna aparte para
 * marcarla como invalidada.
 */
class ClienteAtestacionTest extends TestCase
{
    use RefreshDatabase;

    private AgenteToolService $tools;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tools = app(AgenteToolService::class);
        $this->cliente = User::factory()->create(['role' => UserRole::Client]);
    }

    public function test_no_hay_atestacion_vigente_si_el_cliente_nunca_confirmo(): void
    {
        $pendientes = $this->tools->pendientes($this->cliente, 2025);

        $this->assertFalse($pendientes['atestacion_vigente']);
    }

    public function test_queda_vigente_apenas_se_registra(): void
    {
        $this->tools->registrarAtestacion($this->cliente, 2025, 'Sí, confirmo');

        $pendientes = $this->tools->pendientes($this->cliente, 2025);

        $this->assertTrue($pendientes['atestacion_vigente']);
    }

    public function test_una_atestacion_de_otro_ano_fiscal_no_cuenta(): void
    {
        $this->tools->registrarAtestacion($this->cliente, 2024, 'Sí, confirmo');

        $pendientes = $this->tools->pendientes($this->cliente, 2025);

        $this->assertFalse($pendientes['atestacion_vigente']);
    }

    /**
     * El caso central de la decisión de negocio: agregar algo nuevo después
     * de cerrar invalida la confirmación anterior — sin una columna aparte,
     * solo comparando timestamps. Congela el reloj en cada paso (en vez de
     * confiar en que dos escrituras reales queden en segundos distintos)
     * para que la comparación de timestamps no sea flaky por precisión de
     * columna `timestamp` (sin fracción de segundo).
     */
    public function test_un_cambio_posterior_a_la_atestacion_la_invalida(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        $this->tools->registrarAtestacion($this->cliente, 2025, 'Sí, confirmo');
        $this->assertTrue($this->tools->pendientes($this->cliente, 2025)['atestacion_vigente']);

        Carbon::setTestNow('2026-01-01 10:05:00');
        HistorialCambio::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'tax_year' => 2025,
            'campo' => 'venta_residencia_principal', 'valor_anterior' => null, 'valor_nuevo' => 'x',
            'source' => 'agente_ia',
        ]);

        $pendientes = $this->tools->pendientes($this->cliente, 2025);

        $this->assertFalse($pendientes['atestacion_vigente']);

        Carbon::setTestNow();
    }

    public function test_volver_a_confirmar_despues_de_invalidarse_la_deja_vigente_otra_vez(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        $this->tools->registrarAtestacion($this->cliente, 2025, 'Sí, confirmo');

        Carbon::setTestNow('2026-01-01 10:05:00');
        HistorialCambio::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'tax_year' => 2025,
            'campo' => 'venta_residencia_principal', 'valor_anterior' => null, 'valor_nuevo' => 'x',
            'source' => 'agente_ia',
        ]);
        $this->assertFalse($this->tools->pendientes($this->cliente, 2025)['atestacion_vigente']);

        Carbon::setTestNow('2026-01-01 10:10:00');
        $this->tools->registrarAtestacion($this->cliente, 2025, 'Sí, confirmo de nuevo');

        $this->assertTrue($this->tools->pendientes($this->cliente, 2025)['atestacion_vigente']);

        Carbon::setTestNow();
    }
}
