<?php

namespace Tests\Feature\Expediente;

use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CampoCliente;
use App\Models\CampoDerivationLog;
use App\Models\Documento;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Expediente\EstadosExpediente;
use App\Support\TaxFieldCatalog;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F5.2b: la pestaña Historial (eventos del expediente y datos del formulario
 * anterior, de solo lectura). Los casos de la ficha vieja se movieron aquí.
 */
class FichaHistorialTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    private function crearCampo(User $cliente, string $campo = 'impuestos_retenidos', array $overrides = []): CampoCliente
    {
        return CampoCliente::query()->create(array_merge([
            'user_id' => $cliente->id,
            'forma' => TaxFieldCatalog::formaAlmacen(2025, $campo, 'form_1040'),
            'tax_year' => 2025,
            'campo' => $campo,
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => 1000,
            'estado' => 'recibido',
            'source' => 'agente_ia',
        ], $overrides));
    }

    /**
     * Petición Inertia (XHR) con las props diferidas pedidas, como las serializa el navegador.
     *
     * @return array<string, mixed>
     */
    private function props(User $actor, User $cliente, string $parcial, int $anio = 2025): array
    {
        return $this->actingAs($actor)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'ficha/historial',
                'X-Inertia-Partial-Data' => $parcial,
            ])
            ->get(route('clientes.ficha.historial', ['cliente' => $cliente, 'tax_year' => $anio]))
            ->assertOk()
            ->json('props');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(CatalogoFiscalSeeder::class);
    }

    public function test_el_historial_carga_con_campos_sensibles_con_acentos(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // Enmascarar por bytes partía el carácter acentuado y rompía el JSON de toda la respuesta.
        $this->crearCampo($cliente, 'info_conyuge', [
            'valor_texto' => ['nombre_completo' => 'María López', 'ssn' => '123-45-6789'],
        ]);

        $props = $this->props($admin, $cliente, 'anterior');
        $conyuge = collect($props['anterior']['campos'])->firstWhere('campo', 'info_conyuge');

        $this->assertSame('*******ópez', $conyuge['valor']['nombre_completo']);
        $this->assertSame('*******6789', $conyuge['valor']['ssn']);
    }

    public function test_el_historial_expone_subcampos_y_tipo_dato_para_mostrar_objetos_correctamente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->crearCampo($cliente, 'info_dependientes', [
            'forma' => 'transversal',
            'valor_texto' => [['nombre_completo' => 'Kid One', 'fecha_nacimiento' => '2015-01-01', 'ssn' => '111-22-3333']],
        ]);

        $anterior = $this->props($admin, $cliente, 'anterior')['anterior'];

        $dependientes = collect($anterior['campos'])->firstWhere('campo', 'info_dependientes');
        $this->assertContains('relacion', $dependientes['subcampos']);
        $this->assertContains('meses_en_hogar', $dependientes['subcampos']);
        $this->assertSame('array_object', $dependientes['tipo_dato']);

        $estadoCivil = collect($anterior['catalogoDisponible'])->firstWhere('campo', 'estado_civil');
        $this->assertNotNull($estadoCivil);
        $this->assertContains('casado_al_31_dic', $estadoCivil['subcampos']);
        $this->assertSame('object', $estadoCivil['tipo_dato']);
    }

    public function test_el_historial_expone_los_derivation_logs_del_ano(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $documento = Documento::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'file_path' => 'clientes/w2.pdf',
            'file_original_name' => 'w2.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 10,
            'formato' => 'pdf',
            'estado_validacion' => 'recibido',
        ]);

        $faltante = ['forma' => 'form_1040', 'campo' => 'salarios_medicare', 'subcampo' => null, 'descripcion' => null, 'acumulable' => true];
        CampoDerivationLog::query()->create([
            'user_id' => $cliente->id,
            'tax_year' => 2025,
            'documento_id' => $documento->id,
            'documento_campo' => 'w2',
            'relaciones_esperadas' => [$faltante],
            'revelados_recibidos' => [],
            'relaciones_faltantes' => [$faltante],
        ]);

        $logs = $this->props($preparador, $cliente, 'anterior')['anterior']['derivationLogs'];

        $this->assertCount(1, $logs);
        $this->assertSame('w2', $logs[0]['documento_campo']);
        $this->assertSame('w2.pdf', $logs[0]['documento_nombre']);
        $this->assertCount(1, $logs[0]['relaciones_faltantes']);
    }

    public function test_los_datos_del_formulario_anterior_son_de_solo_lectura_en_la_ficha(): void
    {
        $this->assertFalse(Route::has('clientes.campos.update'));
        $this->assertFalse(Route::has('clientes.campos.destroy'));
        $this->assertTrue(Route::has('clientes.campos.historial'));
        $this->assertTrue(Route::has('clientes.campos.reveal'));
    }

    public function test_anterior_es_null_para_un_cliente_que_solo_uso_la_entrevista_nueva(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();

        $props = $this->props($preparador, $cliente, 'anterior', (int) config('tax.current_tax_year'));

        $this->assertArrayHasKey('anterior', $props);
        $this->assertNull($props['anterior']);
    }

    public function test_los_eventos_salen_del_mas_nuevo_al_mas_viejo_y_sin_valores(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $anio = (int) config('tax.current_tax_year');
        $expediente = app(EstadosExpediente::class)->recalcular($cliente, $anio);
        $expediente->eventos()->delete();

        $registrar = app(EstadosExpediente::class);
        $registrar->registrarCambio($expediente, EstadoExpediente::EnRevision, $preparador, TipoEventoExpediente::Estado, 'Primer paso', ['campo' => 'x']);
        $registrar->registrarCambio($expediente->fresh(), EstadoExpediente::PreparadoEnTaxwise, $preparador, TipoEventoExpediente::Estado, 'Segundo paso');

        $eventos = $this->props($preparador, $cliente, 'eventos', $anio)['eventos'];

        $this->assertCount(2, $eventos);
        $this->assertSame('preparado_en_taxwise', $eventos[0]['estado_nuevo']);
        $this->assertSame('en_revision', $eventos[1]['estado_nuevo']);
        $this->assertSame($preparador->name, $eventos[0]['actor']);
        $this->assertEqualsCanonicalizing(
            ['id', 'tipo', 'tipo_label', 'estado_anterior', 'estado_nuevo', 'estado_nuevo_label', 'actor', 'motivo', 'creado_en'],
            array_keys($eventos[0]),
        );
        $this->assertArrayNotHasKey('datos', $eventos[0]);
    }

    public function test_sin_expediente_los_eventos_salen_vacios(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->assertSame([], $this->props($admin, $cliente, 'eventos')['eventos']);
    }
}
