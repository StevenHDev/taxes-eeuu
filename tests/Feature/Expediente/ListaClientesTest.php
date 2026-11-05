<?php

namespace Tests\Feature\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\UserRole;
use App\Models\Expediente\Expediente;
use App\Models\User;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

class ListaClientesTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
    }

    public function test_el_preparador_ve_solo_sus_clientes(): void
    {
        [, , $preparador] = $this->clienteConExpediente();
        User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($preparador)
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('clientes/index')->has('clientes', 1));
    }

    public function test_un_cliente_sin_entrevista_sale_como_invitado(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)
            ->get(route('clientes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('clientes.0.expediente.estado', 'invitado')
                ->where('clientes.0.expediente.desde', null)
                ->where('clientes.0.expediente.vence_en', null)
                ->where('clientes.0.perfil', [])
                ->where('clientes.0.canal_inicio', null)
                ->where('clientes.0.excepciones', ['total' => 0, 'bloqueantes' => 0])
                ->where('clientes.0.documentos', null));
    }

    public function test_cada_cliente_trae_estado_perfil_canal_excepciones_y_vencimiento(): void
    {
        [$cliente, $entrevista, $preparador] = $this->clienteConExpediente();
        $anio = (int) config('tax.current_tax_year');
        $item = $this->item($entrevista, 'w2', EstadoDocumento::Leido);
        $this->excepcion($entrevista, 'R-01', SeveridadRegla::Bloqueante, $item);
        $this->excepcion($entrevista, 'R-06', SeveridadRegla::Revision, $item);

        Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $anio)->firstOrFail()
            ->update(['estado' => EstadoExpediente::PorValidar, 'perfil' => ['asalariado', 'dependientes'], 'vence_en' => '2026-04-15']);

        $this->actingAs($preparador)
            ->get(route('clientes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('clientes.0.id', $cliente->id)
                ->where('clientes.0.expediente.estado', 'por_validar')
                ->where('clientes.0.expediente.turno', 'preparador')
                ->where('clientes.0.expediente.vence_en', '2026-04-15')
                ->has('clientes.0.expediente.label')
                ->has('clientes.0.expediente.desde')
                ->where('clientes.0.perfil', ['asalariado', 'dependientes'])
                ->where('clientes.0.canal_inicio', 'portal')
                ->where('clientes.0.excepciones', ['total' => 2, 'bloqueantes' => 1])
                ->has('clientes.0.documentos.total'));
    }

    public function test_el_filtro_de_estado_valido_se_devuelve_y_el_invalido_es_nulo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)
            ->get(route('clientes.index', ['estado' => 'por_validar']))
            ->assertInertia(fn (Assert $page) => $page->where('filtroEstado', 'por_validar'));

        $this->actingAs($admin)
            ->get(route('clientes.index', ['estado' => 'inventado']))
            ->assertInertia(fn (Assert $page) => $page->where('filtroEstado', null));

        $this->actingAs($admin)
            ->get(route('clientes.index'))
            ->assertInertia(fn (Assert $page) => $page->where('filtroEstado', null));
    }

    public function test_trae_los_estados_y_perfiles_en_orden_y_el_ano_actual(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)
            ->get(route('clientes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('estados', 12)
                ->where('estados.0.valor', 'invitado')
                ->where('estados.11.valor', 'rechazado')
                ->has('perfiles', 8)
                ->where('perfiles.0.valor', 'asalariado')
                ->where('taxYearActual', (int) config('tax.current_tax_year')));
    }

    public function test_ya_no_trae_nivel_de_riesgo_ni_formas_ni_estado_general(): void
    {
        [, , $preparador] = $this->clienteConExpediente();

        $this->actingAs($preparador)
            ->get(route('clientes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('formas')
                ->missing('clientes.0.formas')
                ->missing('clientes.0.estado_general')
                ->missing('clientes.0.nivel_riesgo')
                ->missing('clientes.0.nivel_riesgo_label')
                ->missing('clientes.0.nivel_riesgo_fuente'));
    }

    public function test_un_cliente_no_entra_a_la_lista(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->get(route('clientes.index'))->assertForbidden();
    }
}
