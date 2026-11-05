<?php

namespace Tests\Feature\Expediente;

use App\Enums\Expediente\EstadoExpediente;
use App\Enums\UserRole;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Expediente\Diagnostico;
use App\Services\Expediente\DiagnosticoExpediente;
use App\Services\Expediente\EstadosExpediente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

class ResumenTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
    }

    private function conEstado(User $cliente, EstadoExpediente $estado): void
    {
        Expediente::query()->where('user_id', $cliente->id)->update(['estado' => $estado]);
    }

    public function test_el_resumen_entrega_la_ficha_y_el_resumen_diferido(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        app(EstadosExpediente::class)->recalcular($cliente, (int) config('tax.current_tax_year'));

        $this->actingAs($preparador)
            ->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ficha/resumen')
                ->where('ficha.cliente.id', $cliente->id)
                ->missing('resumen')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('resumen.diagnostico.consentimiento', true)
                    ->where('resumen.entrevista.estado', 'confirmada')
                    ->where('resumen.entrevista.canal_inicio', 'portal')
                    ->where('resumen.consentimiento.version', 1)
                    ->has('resumen.consentimiento.provisional')
                    ->has('resumen.eventos')
                    ->has('resumen.accion_principal')));
    }

    public function test_los_eventos_son_los_30_mas_nuevos_y_sin_datos_sensibles(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $anio = (int) config('tax.current_tax_year');
        $expediente = app(EstadosExpediente::class)->recalcular($cliente, $anio);
        $servicio = app(EstadosExpediente::class);

        foreach (range(1, 35) as $i) {
            $servicio->registrarCambio($expediente, $i % 2 ? EstadoExpediente::EnConsulta : EstadoExpediente::PorValidar, null);
        }

        $this->actingAs($preparador)
            ->get(route('clientes.show', $cliente))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('resumen.eventos', 30)
                ->has('resumen.eventos.0', fn (Assert $e) => $e
                    ->hasAll(['id', 'tipo', 'tipo_label', 'estado_anterior', 'estado_nuevo', 'estado_nuevo_label', 'actor', 'motivo', 'creado_en']))));
    }

    /**
     * La acción apunta solo a rutas que existen: cada rama se comprueba con
     * la ruta de su pestaña, si ya está registrada.
     */
    private function comprobarAccion(EstadoExpediente $estado, ?string $clave, ?string $ruta): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $anio = (int) config('tax.current_tax_year');

        $accion = $this->accionPara($cliente, $preparador, $estado);

        if ($clave === null || ! Route::has($ruta)) {
            $this->assertNull($accion);

            return;
        }

        $this->assertSame($clave, $accion['clave']);
        $this->assertSame(route($ruta, ['cliente' => $cliente->id, 'tax_year' => $anio]), $accion['url']);
    }

    public function test_por_validar_la_accion_principal_lleva_a_documentos(): void
    {
        $this->comprobarAccion(EstadoExpediente::PorValidar, 'documentos', 'clientes.ficha.documentos');
    }

    public function test_listo_para_preparar_la_accion_principal_lleva_a_la_revision(): void
    {
        $this->comprobarAccion(EstadoExpediente::ListoParaPreparar, 'revision', 'clientes.revision.show');
    }

    public function test_en_revision_la_accion_principal_lleva_a_la_hoja_o_a_la_revision(): void
    {
        Route::has('clientes.hoja-taxwise.show')
            ? $this->comprobarAccion(EstadoExpediente::EnRevision, 'hoja', 'clientes.hoja-taxwise.show')
            : $this->comprobarAccion(EstadoExpediente::EnRevision, 'revision', 'clientes.revision.show');
    }

    public function test_en_un_estado_del_cliente_no_hay_accion_principal(): void
    {
        $this->comprobarAccion(EstadoExpediente::EnConsulta, null, null);
    }

    public function test_tras_presentar_no_hay_accion_principal(): void
    {
        $this->comprobarAccion(EstadoExpediente::Presentado, null, null);
    }

    /**
     * @return array{clave: string, url: string}|null
     */
    private function accionPara(User $cliente, User $preparador, EstadoExpediente $estado): ?array
    {
        $this->app->instance(
            DiagnosticoExpediente::class,
            new class($estado) extends DiagnosticoExpediente
            {
                public function __construct(private readonly EstadoExpediente $forzado) {}

                public function para(User $cliente, int $taxYear): Diagnostico
                {
                    return new Diagnostico($this->forzado, true, true, false, true, [], [], 0, 0, 0, 0, 0);
                }
            },
        );

        $accion = null;

        $this->actingAs($preparador)
            ->get(route('clientes.show', $cliente))
            ->assertInertia(function (Assert $page) use (&$accion) {
                $page->loadDeferredProps(function (Assert $reload) use (&$accion) {
                    $reload->where('resumen.accion_principal', function ($valor) use (&$accion) {
                        $accion = $valor === null ? null : collect($valor)->all();

                        return true;
                    });
                });
            });

        return $accion;
    }

    public function test_otro_preparador_y_el_cliente_reciben_403_y_el_administrador_entra(): void
    {
        [$cliente] = $this->clienteConExpediente();
        $otro = User::factory()->create(['role' => UserRole::Preparer]);
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($otro)->get(route('clientes.show', $cliente))->assertForbidden();
        $this->actingAs($cliente)->get(route('clientes.show', $cliente))->assertForbidden();
        $this->actingAs($admin)->get(route('clientes.show', $cliente))->assertOk();
    }
}
