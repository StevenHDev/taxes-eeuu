<?php

namespace Tests\Unit\Support;

use App\Enums\TaxForm;
use App\Models\CampoCliente;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `TaxFieldCatalog::pendientesPara()` es pura lógica de catálogo (sin HTTP),
 * pero sí necesita el catálogo real sembrado en BD — de ahí que extienda el
 * TestCase de Laravel (con RefreshDatabase) en vez del PHPUnit\Framework\TestCase
 * plano que usan las calculadoras de Reglas.
 */
class TaxFieldCatalogPendientesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deduplica_campos_transversales_entre_varias_formas(): void
    {
        $cliente = User::factory()->create();

        $pendientes = TaxFieldCatalog::pendientesPara(2025, [TaxForm::ScheduleC, TaxForm::ScheduleE], $cliente->id);

        $transversales = collect($pendientes)->filter(fn (array $p) => $p['campo'] === 'identificacion_ssn_itin');

        $this->assertCount(1, $transversales);
        $this->assertSame('transversal', $transversales->first()['forma']);
    }

    public function test_no_deduplica_campos_propios_de_forma_compartidos_entre_formas_de_negocio(): void
    {
        $cliente = User::factory()->create();

        $pendientes = TaxFieldCatalog::pendientesPara(2025, [TaxForm::ScheduleC, TaxForm::ScheduleE], $cliente->id);

        $estadosBancarios = collect($pendientes)->filter(fn (array $p) => $p['campo'] === 'estados_bancarios');

        $this->assertCount(2, $estadosBancarios);
        $this->assertEqualsCanonicalizing(['schedule_c', 'schedule_e'], $estadosBancarios->pluck('forma')->all());
    }

    public function test_un_campo_ya_recibido_no_aparece_como_pendiente(): void
    {
        $cliente = User::factory()->create();

        CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'identificacion_ssn_itin',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => '123-45-6789',
            'estado' => 'recibido',
            'source' => 'agente_ia',
        ]);

        $pendientes = TaxFieldCatalog::pendientesPara(2025, [TaxForm::Form1040], $cliente->id);

        $this->assertFalse(collect($pendientes)->contains(fn (array $p) => $p['campo'] === 'identificacion_ssn_itin'));
    }

    public function test_incluye_campos_opcionales_marcados_con_su_propio_flag(): void
    {
        $cliente = User::factory()->create();

        $pendientes = collect(TaxFieldCatalog::pendientesPara(2025, [TaxForm::Form1040], $cliente->id));

        $opcional = $pendientes->firstWhere('campo', 'declaracion_anio_anterior');

        $this->assertNotNull($opcional);
        $this->assertFalse($opcional['obligatorio']);
    }

    /**
     * Un opcional declinado (estado no_aplica) es una respuesta resuelta, igual
     * que uno recibido — no debe seguir apareciendo en `pendientes` (ver
     * App\Enums\FieldState::NoAplica).
     */
    public function test_un_campo_marcado_no_aplica_no_aparece_como_pendiente(): void
    {
        $cliente = User::factory()->create();

        CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'documentos_extra',
            'tax_year' => 2025,
            'campo' => 'declaracion_anio_anterior',
            'tipo_campo' => 'documento',
            'modo' => 'no_aplica',
            'valor_texto' => null,
            'estado' => 'no_aplica',
            'source' => 'agente_ia',
        ]);

        $pendientes = TaxFieldCatalog::pendientesPara(2025, [TaxForm::Form1040], $cliente->id);

        $this->assertFalse(collect($pendientes)->contains(fn (array $p) => $p['campo'] === 'declaracion_anio_anterior'));
    }
}
