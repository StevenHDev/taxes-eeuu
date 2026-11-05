<?php

namespace Tests\Feature\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Enums\UserRole;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\Lectura\DocumentoDatoCambio;
use App\Models\User;
use App\Services\Expediente\PanelExpedientes;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    private int $anio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        $this->anio = (int) config('tax.current_tax_year');
        App::setLocale('es');
    }

    private function expedienteDe(User $cliente): Expediente
    {
        return Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $this->anio)->firstOrFail();
    }

    private function evento(Expediente $e, ?EstadoExpediente $de, EstadoExpediente $a, string $cuando = 'now', TipoEventoExpediente $tipo = TipoEventoExpediente::Estado): ExpedienteEvento
    {
        $evento = ExpedienteEvento::query()->create(['expediente_id' => $e->id, 'tipo' => $tipo, 'estado_anterior' => $de, 'estado_nuevo' => $a]);
        $evento->forceFill(['created_at' => now()->modify($cuando)])->save();

        return $evento;
    }

    /**
     * @return array<string, mixed>
     */
    private function panel(User $actor): array
    {
        return app(PanelExpedientes::class)->resumen($actor, $this->anio);
    }

    public function test_cuenta_los_expedientes_por_estado_e_invitado_suma_a_quien_no_tiene_expediente(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $this->expedienteDe($cliente)->update(['estado' => EstadoExpediente::PorValidar]);
        User::factory()->count(2)->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $panel = $this->panel($preparador);
        $porEstado = collect($panel['por_estado']);

        $this->assertCount(12, $porEstado);
        $this->assertSame(array_map(fn ($e) => $e->value, EstadoExpediente::cases()), $porEstado->pluck('estado')->all());
        $this->assertSame(2, $porEstado->firstWhere('estado', 'invitado')['cantidad']);
        $this->assertSame(1, $porEstado->firstWhere('estado', 'por_validar')['cantidad']);
        $this->assertSame(route('clientes.index', ['estado' => 'por_validar', 'tax_year' => $this->anio]), $porEstado->firstWhere('estado', 'por_validar')['url']);
        $this->assertSame('Por validar', $porEstado->firstWhere('estado', 'por_validar')['label']);
    }

    public function test_las_colas_cuentan_listos_por_validar_consulta_y_excepciones(): void
    {
        [$a, $entrevistaA, $preparador] = $this->clienteConExpediente();
        [$b] = $this->clienteConExpediente(preparador: $preparador);
        [$c] = $this->clienteConExpediente(preparador: $preparador);
        $item = $this->item($entrevistaA, 'w2', EstadoDocumento::Leido);
        $this->excepcion($entrevistaA, 'R-01', SeveridadRegla::Bloqueante, $item);
        $this->excepcion($entrevistaA, 'R-06', SeveridadRegla::Revision, $item);

        $this->expedienteDe($a)->update(['estado' => EstadoExpediente::ListoParaPreparar]);
        $this->expedienteDe($b)->update(['estado' => EstadoExpediente::PorValidar]);
        $this->expedienteDe($c)->update(['estado' => EstadoExpediente::EnConsulta]);

        $colas = $this->panel($preparador)['colas'];

        $this->assertSame(1, $colas['listos']);
        $this->assertSame(1, $colas['por_validar']);
        $this->assertSame(1, $colas['en_consulta']);
        $this->assertSame(2, $colas['excepciones']);
        $this->assertSame(1, $colas['bloqueantes']);
        $this->assertSame(route('clientes.index', ['estado' => 'listo_para_preparar', 'tax_year' => $this->anio]), $colas['urls']['listos']);
        $this->assertSame(route('clientes.excepciones.index'), $colas['urls']['excepciones']);
    }

    public function test_vencen_pronto_ordena_limita_y_excluye_presentados_y_lejanos(): void
    {
        [$cercano, , $preparador] = $this->clienteConExpediente();
        [$lejano] = $this->clienteConExpediente(preparador: $preparador);
        [$presentado] = $this->clienteConExpediente(preparador: $preparador);
        [$medio] = $this->clienteConExpediente(preparador: $preparador);
        $this->expedienteDe($cercano)->update(['vence_en' => now()->addDays(5)->toDateString()]);
        $this->expedienteDe($medio)->update(['vence_en' => now()->addDays(20)->toDateString()]);
        $this->expedienteDe($lejano)->update(['vence_en' => now()->addDays(60)->toDateString()]);
        $this->expedienteDe($presentado)->update(['vence_en' => now()->addDays(2)->toDateString(), 'estado' => EstadoExpediente::Presentado]);

        $vencen = $this->panel($preparador)['vencen_pronto'];

        $this->assertSame([$cercano->id, $medio->id], array_column($vencen, 'cliente_id'));
        $this->assertSame(5, $vencen[0]['dias']);
        $this->assertSame($cercano->name, $vencen[0]['nombre']);
        $this->assertSame(route('clientes.show', ['cliente' => $cercano->id, 'tax_year' => $this->anio]), $vencen[0]['url']);
    }

    public function test_los_avisos_salen_de_los_eventos_de_estado_y_respetan_los_14_dias(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $expediente = $this->expedienteDe($cliente);
        ExpedienteEvento::query()->delete();

        $this->evento($expediente, EstadoExpediente::EnEntrevista, EstadoExpediente::EnRecoleccion, '-5 days');
        $this->evento($expediente, EstadoExpediente::EnRecoleccion, EstadoExpediente::PorValidar, '-4 days');
        $this->evento($expediente, EstadoExpediente::EnConsulta, EstadoExpediente::PorValidar, '-3 days');
        $this->evento($expediente, EstadoExpediente::PorValidar, EstadoExpediente::ListoParaPreparar, '-2 days');
        // Gana "listo" aunque venga de una consulta.
        $this->evento($expediente, EstadoExpediente::EnConsulta, EstadoExpediente::ListoParaPreparar, '-1 days');
        // Sin aviso: ni es de estado ni cae en las reglas.
        $this->evento($expediente, EstadoExpediente::PorValidar, EstadoExpediente::EnConsulta, '-1 hour');
        $this->evento($expediente, null, EstadoExpediente::EnRevision, '-1 hour', TipoEventoExpediente::Decision);
        // Fuera de los 14 días.
        $this->evento($expediente, EstadoExpediente::EnEntrevista, EstadoExpediente::EnRecoleccion, '-15 days');

        $avisos = $this->panel($preparador)['avisos'];

        $this->assertSame(['listo_para_preparar', 'listo_para_preparar', 'respondio_consulta', 'subio_documentos', 'termino_entrevista'], array_column($avisos, 'tipo'));
        $this->assertSame("{$cliente->name} está lista para preparar", $avisos[0]['texto']);
        $this->assertSame($cliente->id, $avisos[0]['cliente_id']);
        $this->assertSame($cliente->name, $avisos[0]['nombre']);
        $this->assertNotEmpty($avisos[0]['creado_en']);
    }

    public function test_los_avisos_se_limitan_a_20(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $expediente = $this->expedienteDe($cliente);

        foreach (range(1, 25) as $i) {
            $this->evento($expediente, EstadoExpediente::EnEntrevista, EstadoExpediente::EnRecoleccion, "-{$i} hours");
        }

        $this->assertCount(20, $this->panel($preparador)['avisos']);
    }

    public function test_los_tiempos_se_calculan_con_los_eventos_sembrados(): void
    {
        [$cliente, $entrevista, $preparador] = $this->clienteConExpediente();
        $expediente = $this->expedienteDe($cliente);
        ExpedienteEvento::query()->delete();
        $entrevista->update(['iniciada_en' => now()->subDays(10)]);

        $this->evento($expediente, EstadoExpediente::PorValidar, EstadoExpediente::EnConsulta, '-8 days');
        $this->evento($expediente, EstadoExpediente::EnConsulta, EstadoExpediente::PorValidar, '-7 days');
        $this->evento($expediente, EstadoExpediente::PorValidar, EstadoExpediente::ListoParaPreparar, '-4 days');
        $this->evento($expediente, EstadoExpediente::ListoParaPreparar, EstadoExpediente::EnRevision, '-3 hours');
        $this->evento($expediente, EstadoExpediente::EnRevision, EstadoExpediente::PreparadoEnTaxwise, '-1 hour');

        $item = $this->item($entrevista, 'w2', EstadoDocumento::Leido);
        $dato = $this->datoLeido($item, 'box1', '1000');
        foreach ([1, 2, 3] as $i) {
            DocumentoDatoCambio::query()->create(['dato_id' => $dato->id, 'valor_anterior' => 'a', 'valor_nuevo' => 'b', 'motivo' => "m{$i}"]);
        }

        $tiempos = $this->panel($preparador)['tiempos'];

        $this->assertSame(1, $tiempos['muestra']);
        $this->assertEqualsWithDelta(6.0, $tiempos['dias_hasta_listo'], 0.1);
        $this->assertEqualsWithDelta(2.0, $tiempos['horas_preparacion'], 0.1);
        $this->assertEqualsWithDelta(1.0, $tiempos['consultas_por_expediente'], 0.01);
        $this->assertEqualsWithDelta(3.0, $tiempos['correcciones_por_expediente'], 0.01);
    }

    public function test_sin_expedientes_los_tiempos_son_nulos(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->assertNull($this->panel($preparador)['tiempos']);
    }

    public function test_el_preparador_ve_solo_sus_clientes_y_el_administrador_todos(): void
    {
        [$propio, , $preparador] = $this->clienteConExpediente();
        [$ajeno] = $this->clienteConExpediente();
        $this->expedienteDe($propio)->update(['estado' => EstadoExpediente::ListoParaPreparar]);
        $this->expedienteDe($ajeno)->update(['estado' => EstadoExpediente::ListoParaPreparar]);

        $this->assertSame(1, $this->panel($preparador)['colas']['listos']);
        $this->assertSame([$propio->id], array_column($this->panel($preparador)['ultimos_clientes'], 'id'));

        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->assertSame(2, $this->panel($admin)['colas']['listos']);
    }

    public function test_el_dashboard_entrega_el_panel_diferido_y_el_ano(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $this->expedienteDe($cliente)->update(['estado' => EstadoExpediente::ListoParaPreparar]);

        $this->actingAs($preparador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('taxYearActual', $this->anio)
                ->missing('panel')
                ->missing('resumen')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('panel.colas.listos', 1)
                    ->has('panel.por_estado', 12)));
    }
}
