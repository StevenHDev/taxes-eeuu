<?php

namespace Tests\Feature\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\UserRole;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Expediente\EstadosExpediente;
use App\Services\Expediente\FichaCliente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F5.1b: el encabezado que comparten todas las pestañas de la ficha.
 */
class FichaClienteTest extends TestCase
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
        App::setLocale('es');
    }

    public function test_la_forma_del_encabezado(): void
    {
        [$cliente, $e, $preparador] = $this->clienteConExpediente();
        $cliente->update(['phone' => '+15550001111']);
        $w2 = $e->documentos()->where('documento_codigo', 'w2')->firstOrFail();
        $w2->update(['estado' => EstadoDocumento::Leido]);
        // Lo demás (la identificación, que se pide siempre) ya está validado: solo queda el W-2 por validar.
        $e->documentos()->whereKeyNot($w2->id)->update(['estado' => EstadoDocumento::Validado]);
        $this->excepcion($e, 'R-04', SeveridadRegla::Bloqueante);
        app(EstadosExpediente::class)->recalcular($cliente, $this->anio);

        $ficha = app(FichaCliente::class)->encabezado($cliente->fresh(), $this->anio, $preparador);

        $this->assertSame(['id' => $cliente->id, 'name' => $cliente->name, 'email' => $cliente->email, 'phone' => '+15550001111', 'role' => 'client', 'es_prueba' => false, 'archivado' => false], $ficha['cliente']);
        $this->assertSame($this->anio, $ficha['tax_year']);
        $this->assertSame($this->anio, $ficha['anios'][0]);
        $this->assertSame(['existe', 'estado', 'label', 'desde', 'turno', 'vence_en', 'cambios_tras_presentar'], array_keys($ficha['expediente']));
        $this->assertTrue($ficha['expediente']['existe']);
        $this->assertSame('por_validar', $ficha['expediente']['estado']);
        $this->assertSame('Por validar', $ficha['expediente']['label']);
        $this->assertSame('preparador', $ficha['expediente']['turno']);
        $this->assertSame('2026-04-15', $ficha['expediente']['vence_en']);
        $this->assertFalse($ficha['expediente']['cambios_tras_presentar']);
        $this->assertNotNull($ficha['expediente']['desde']);
        $this->assertSame(['excepciones', 'bloqueantes', 'documentos_por_validar', 'documentos_faltan'], array_keys($ficha['conteos']));
        $this->assertFalse($ficha['puede_administrar']);
    }

    public function test_los_conteos(): void
    {
        [$cliente, $e, $preparador] = $this->clienteConExpediente();
        $w2 = $e->documentos()->where('documento_codigo', 'w2')->firstOrFail();
        $w2->update(['estado' => EstadoDocumento::Leido]);
        $e->documentos()->whereKeyNot($w2->id)->update(['estado' => EstadoDocumento::Pendiente]);
        $faltan = $e->documentos()->vigentes()->where('estado', EstadoDocumento::Pendiente)->count();
        $this->excepcion($e, 'R-04', SeveridadRegla::Bloqueante);
        $this->excepcion($e, 'R-07', SeveridadRegla::Informativa);

        $conteos = app(FichaCliente::class)->encabezado($cliente, $this->anio, $preparador)['conteos'];

        $this->assertSame(2, $conteos['excepciones']);
        $this->assertSame(1, $conteos['bloqueantes']);
        $this->assertSame(1, $conteos['documentos_por_validar']);
        $this->assertSame($faltan, $conteos['documentos_faltan']);
    }

    public function test_las_pestanas_solo_incluyen_las_rutas_que_existen(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();

        $ficha = app(FichaCliente::class)->encabezado($cliente, $this->anio, $preparador);

        $esperadas = collect(FichaCliente::PESTANAS)->filter(fn (string $ruta) => Route::has($ruta))->keys()->all();
        $this->assertSame($esperadas, array_column($ficha['pestanas'], 'clave'));
        $this->assertContains('resumen', $esperadas);
        $this->assertSame(
            route('clientes.show', ['cliente' => $cliente->id, 'tax_year' => $this->anio]),
            $ficha['pestanas'][0]['url'],
        );

        // Una pestaña cuya ruta todavía no existe no aparece (las demás se llenan en otras subtareas).
        $this->assertFalse(Route::has('clientes.ficha.sin-llenar'));
        $this->assertNotContains('sin-llenar', array_column($ficha['pestanas'], 'clave'));
    }

    public function test_sin_expediente_existe_es_falso_y_el_estado_sale_del_diagnostico(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $ficha = app(FichaCliente::class)->encabezado($cliente, $this->anio, $admin);

        $this->assertFalse($ficha['expediente']['existe']);
        $this->assertSame('invitado', $ficha['expediente']['estado']);
        $this->assertSame('cliente', $ficha['expediente']['turno']);
        $this->assertNull($ficha['expediente']['desde']);
        $this->assertSame(0, $ficha['conteos']['excepciones']);
        $this->assertTrue($ficha['puede_administrar']);
        $this->assertSame(0, Expediente::query()->count());
    }

    public function test_un_cliente_archivado_se_marca(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        $cliente->delete();

        $ficha = app(FichaCliente::class)->encabezado($cliente, $this->anio, User::factory()->create(['role' => UserRole::Administrator]));

        $this->assertTrue($ficha['cliente']['archivado']);
        $this->assertTrue($ficha['cliente']['es_prueba']);
    }
}
