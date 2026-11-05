<?php

namespace Tests\Feature\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\User;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F5.2b: las rutas y props de Entrevista, Documentos, Excepciones e Historial.
 */
class FichaPestanasTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    private int $anio;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(CatalogoFiscalSeeder::class);
        $this->anio = (int) config('tax.current_tax_year');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pestanas(): array
    {
        return [
            'entrevista' => ['clientes.ficha.entrevista', 'ficha/entrevista'],
            'documentos' => ['clientes.ficha.documentos', 'ficha/documentos'],
            'excepciones' => ['clientes.ficha.excepciones', 'ficha/excepciones'],
            'historial' => ['clientes.ficha.historial', 'ficha/historial'],
        ];
    }

    #[DataProvider('pestanas')]
    public function test_cada_pestana_carga_para_el_preparador_asignado_y_el_administrador(string $ruta, string $componente): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        foreach ([$preparador, $admin] as $actor) {
            $this->actingAs($actor)->get(route($ruta, $cliente))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($componente)
                    ->where('ficha.cliente.id', $cliente->id)
                    ->where('ficha.tax_year', $this->anio)
                    ->has('ficha.pestanas'));
        }
    }

    #[DataProvider('pestanas')]
    public function test_cada_pestana_da_403_a_otro_preparador_y_al_cliente(string $ruta, string $componente): void
    {
        [$cliente] = $this->clienteConExpediente();
        $otro = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($otro)->get(route($ruta, $cliente))->assertForbidden();
        $this->actingAs($cliente)->get(route($ruta, $cliente))->assertForbidden();
    }

    #[DataProvider('pestanas')]
    public function test_las_props_diferidas_se_cargan_sin_error(string $ruta, string $componente): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();

        $this->actingAs($preparador)->get(route($ruta, $cliente))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reloaded) => $reloaded->component($componente)));
    }

    public function test_entrevista_y_documentos_traen_la_vista_de_la_entrevista(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();

        foreach (['clientes.ficha.entrevista', 'clientes.ficha.documentos'] as $ruta) {
            $this->actingAs($preparador)->get(route($ruta, $cliente))
                ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $r) => $r->has('entrevista.documentos')));
        }
    }

    public function test_excepciones_filtra_por_cliente_y_por_ano(): void
    {
        [$cliente, $entrevista, $preparador] = $this->clienteConExpediente();
        $this->excepcion($entrevista, 'R-04', SeveridadRegla::Bloqueante);

        $viejaEntrevista = Entrevista::query()->create(['user_id' => $cliente->id, 'tax_year' => $this->anio - 1, 'estado' => EstadoEntrevista::EnCurso, 'canal_inicio' => CanalRespuesta::Portal, 'iniciada_en' => now()]);
        $this->excepcion($viejaEntrevista, 'R-04', SeveridadRegla::Bloqueante);

        [$otro] = $this->clienteConExpediente(preparador: $preparador);
        $this->excepcion($otro->entrevistas()->firstOrFail(), 'R-04', SeveridadRegla::Bloqueante);

        $this->actingAs($preparador)->get(route('clientes.ficha.excepciones', $cliente))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $r) => $r
                ->has('excepciones', 1)
                ->where('excepciones.0.cliente.id', $cliente->id)));

        $this->actingAs($preparador)->get(route('clientes.ficha.excepciones', ['cliente' => $cliente, 'tax_year' => $this->anio - 1]))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $r) => $r->has('excepciones', 1)));
    }
}
