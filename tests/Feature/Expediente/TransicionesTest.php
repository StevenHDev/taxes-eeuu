<?php

namespace Tests\Feature\Expediente;

use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Expediente\RevisionExpediente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F5.3a: las transiciones manuales del expediente (plan de F5, "Estados del
 * expediente y transiciones") y lo que deja cada una en su bitácora.
 */
class TransicionesTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    private int $anio;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        $this->anio = (int) config('tax.current_tax_year');
        App::setLocale('es');
    }

    /**
     * @return array{0: User, 1: Entrevista, 2: User}
     */
    private function listo(): array
    {
        [$cliente, $e, $preparador] = $this->clienteConExpediente(['D1' => 'si', 'G5' => 'no']);

        foreach ($e->documentos()->vigentes()->get() as $item) {
            $item->update(['estado' => EstadoDocumento::Validado]);
        }

        return [$cliente, $e, $preparador];
    }

    private function expediente(User $cliente): Expediente
    {
        return Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $this->anio)->firstOrFail();
    }

    private function enEstado(User $cliente, EstadoExpediente $estado): Expediente
    {
        $expediente = $this->expediente($cliente);
        $expediente->update(['estado' => $estado, 'estado_desde' => now()]);

        return $expediente;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function accion(User $preparador, User $cliente, string $accion, array $extra = []): TestResponse
    {
        return $this->actingAs($preparador)->post(route('clientes.revision.transicion', $cliente), ['tax_year' => $this->anio, 'accion' => $accion, ...$extra]);
    }

    /**
     * Todos los puntos requeridos cumplidos (el caso sin personas ni créditos).
     */
    private function cumplirChecklist(User $cliente, User $preparador): void
    {
        $this->actingAs($preparador)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'single', 'motivo' => 'Soltero'])->assertRedirect();
        $this->actingAs($preparador)->put(route('clientes.revision.punto', [$cliente, 'identidad']), ['tax_year' => $this->anio, 'confirmado' => true])->assertRedirect();
    }

    public function test_empezar_pasa_de_listo_a_en_revision_y_deja_su_evento(): void
    {
        [$cliente, , $preparador] = $this->listo();

        $this->accion($preparador, $cliente, 'empezar')->assertRedirect();

        $expediente = $this->expediente($cliente);
        $this->assertSame(EstadoExpediente::EnRevision, $expediente->estado);

        $evento = $expediente->eventos()->where('estado_nuevo', EstadoExpediente::EnRevision)->latest('id')->firstOrFail();
        $this->assertSame(TipoEventoExpediente::Estado, $evento->tipo);
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $evento->estado_anterior);
        $this->assertSame($preparador->id, $evento->actor_id);
    }

    public function test_empezar_cuando_no_esta_listo_falla_con_los_motivos(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $e->documentos()->where('documento_codigo', 'w2')->firstOrFail()->update(['estado' => EstadoDocumento::Leido]);

        $this->accion($preparador, $cliente, 'empezar')->assertSessionHasErrors('accion');

        $this->assertStringContainsString('1 documento sin validar', session('errors')->first('accion'));
        $this->assertSame(EstadoExpediente::PorValidar, $this->expediente($cliente)->estado);
    }

    public function test_preparado_sin_los_puntos_requeridos_falla_y_con_todos_pasa(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->accion($preparador, $cliente, 'empezar');

        $this->accion($preparador, $cliente, 'preparado')->assertSessionHasErrors('accion');
        $this->assertSame(EstadoExpediente::EnRevision, $this->expediente($cliente)->estado);

        $this->cumplirChecklist($cliente, $preparador);
        $this->accion($preparador, $cliente, 'preparado')->assertRedirect()->assertSessionHasNoErrors();

        $expediente = $this->expediente($cliente);
        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $expediente->estado);
        $this->assertSame($preparador->id, $expediente->eventos()->where('estado_nuevo', EstadoExpediente::PreparadoEnTaxwise)->firstOrFail()->actor_id);
    }

    public function test_a_firma_y_presentado_guardan_la_fecha(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::PreparadoEnTaxwise);

        $this->accion($preparador, $cliente, 'a_firma')->assertRedirect();
        $this->assertSame(EstadoExpediente::EsperandoFirma, $this->expediente($cliente)->estado);

        $fecha = ($this->anio + 1).'-03-01';
        $this->accion($preparador, $cliente, 'presentado', ['fecha' => $fecha])->assertRedirect()->assertSessionHasNoErrors();

        $expediente = $this->expediente($cliente);
        $this->assertSame(EstadoExpediente::Presentado, $expediente->estado);
        $this->assertSame($fecha, $expediente->presentado_en->toDateString());
        $this->assertSame(['fecha' => $fecha], $expediente->eventos()->where('estado_nuevo', EstadoExpediente::Presentado)->firstOrFail()->datos);
    }

    public function test_presentar_directo_desde_preparado_tambien_se_permite(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::PreparadoEnTaxwise);

        $this->accion($preparador, $cliente, 'presentado', ['fecha' => ($this->anio + 1).'-02-10'])->assertRedirect();

        $this->assertSame(EstadoExpediente::Presentado, $this->expediente($cliente)->estado);
    }

    public function test_presentado_sin_fecha_o_con_fecha_futura_falla(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::PreparadoEnTaxwise);

        $this->accion($preparador, $cliente, 'presentado')->assertSessionHasErrors('fecha');
        $this->accion($preparador, $cliente, 'presentado', ['fecha' => now()->addDay()->toDateString()])->assertSessionHasErrors('fecha');

        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $this->expediente($cliente)->estado);
        $this->assertNull($this->expediente($cliente)->presentado_en);
    }

    public function test_presentado_con_una_fecha_anterior_al_cierre_del_anio_fiscal_falla(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::PreparadoEnTaxwise);

        $this->accion($preparador, $cliente, 'presentado', ['fecha' => "{$this->anio}-12-30"])->assertSessionHasErrors('accion');

        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $this->expediente($cliente)->estado);
    }

    public function test_aceptado_rechazado_y_volver_a_revision(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::Presentado);

        $this->accion($preparador, $cliente, 'aceptado')->assertRedirect();
        $this->assertSame(EstadoExpediente::Aceptado, $this->expediente($cliente)->estado);

        $this->enEstado($cliente, EstadoExpediente::Presentado);
        $this->accion($preparador, $cliente, 'rechazado', ['motivo' => 'Código IND-181: el SSN no coincide'])->assertRedirect();

        $expediente = $this->expediente($cliente);
        $this->assertSame(EstadoExpediente::Rechazado, $expediente->estado);
        $this->assertSame('Código IND-181: el SSN no coincide', $expediente->eventos()->where('estado_nuevo', EstadoExpediente::Rechazado)->firstOrFail()->motivo);

        $this->accion($preparador, $cliente, 'volver_a_revision')->assertRedirect();
        $this->assertSame(EstadoExpediente::EnRevision, $this->expediente($cliente)->estado);
    }

    public function test_rechazado_sin_motivo_falla(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::Presentado);

        $this->accion($preparador, $cliente, 'rechazado')->assertSessionHasErrors('motivo');

        $this->assertSame(EstadoExpediente::Presentado, $this->expediente($cliente)->estado);
    }

    public function test_una_accion_prohibida_por_el_estado_da_422_con_el_mensaje(): void
    {
        [$cliente, , $preparador] = $this->listo();

        // Desde "listo para preparar" no se puede aprobar ni marcar aceptado.
        foreach (['preparado', 'a_firma', 'aceptado', 'volver_a_revision'] as $accion) {
            $this->accion($preparador, $cliente, $accion)->assertSessionHasErrors('accion');
        }

        $this->assertStringContainsString('Listo para preparar', session('errors')->first('accion'));
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->expediente($cliente)->estado);

        $this->accion($preparador, $cliente, 'inventada')->assertSessionHasErrors('accion');
    }

    public function test_una_decision_en_preparado_vuelve_a_en_revision_con_evento_reabierto(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::PreparadoEnTaxwise);

        $this->actingAs($preparador)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'mfs', 'motivo' => 'Cambio de decisión'])
            ->assertRedirect();

        $expediente = $this->expediente($cliente);
        $this->assertSame(EstadoExpediente::EnRevision, $expediente->estado);

        $reabierto = $expediente->eventos()->where('tipo', TipoEventoExpediente::Reabierto)->firstOrFail();
        $this->assertSame('Cambió una decisión después de preparar', $reabierto->motivo);
        $this->assertSame($preparador->id, $reabierto->actor_id);
        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $reabierto->estado_anterior);
    }

    public function test_una_decision_en_esperando_firma_tambien_reabre(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::EsperandoFirma);

        $this->actingAs($preparador)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'mfj', 'motivo' => 'Se casó'])->assertRedirect();

        $this->assertSame(EstadoExpediente::EnRevision, $this->expediente($cliente)->estado);
    }

    public function test_despues_de_presentar_las_decisiones_dan_422(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $this->enEstado($cliente, EstadoExpediente::Presentado);
        $persona = app(RespuestaGuardador::class)->agregarPersona($e);

        $this->actingAs($preparador)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'single', 'motivo' => 'x'])
            ->assertSessionHasErrors('accion');
        $this->actingAs($preparador)->put(route('clientes.revision.persona', [$cliente, $persona]), ['tax_year' => $this->anio, 'clasificacion' => 'no_dependiente', 'motivo' => 'x'])
            ->assertSessionHasErrors('accion');

        $this->assertNull($this->expediente($cliente)->estado_civil_irs);
        $this->assertSame(EstadoExpediente::Presentado, $this->expediente($cliente)->estado);
    }

    public function test_la_revision_se_reabre_sola_si_el_diagnostico_deja_de_estar_listo(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $this->accion($preparador, $cliente, 'empezar');
        $this->cumplirChecklist($cliente, $preparador);

        $e->documentos()->where('documento_codigo', 'w2')->firstOrFail()->update(['estado' => EstadoDocumento::Leido]);

        // Aprobar recalcula primero: el expediente vuelve a "por validar" y no se aprueba.
        $this->accion($preparador, $cliente, 'preparado')->assertSessionHasErrors('accion');
        $this->assertSame(EstadoExpediente::PorValidar, $this->expediente($cliente)->estado);
    }

    public function test_el_cliente_no_puede_hacer_transiciones(): void
    {
        [$cliente] = $this->listo();

        $this->accion($cliente, $cliente, 'empezar')->assertForbidden();
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->expediente($cliente)->estado);
    }

    public function test_las_acciones_de_la_vista_siguen_el_estado(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $vista = fn () => app(RevisionExpediente::class)->vista($cliente, $this->anio, false);

        $this->assertSame(['empezar'], array_column($vista()['acciones'], 'accion'));

        $this->accion($preparador, $cliente, 'empezar');
        $acciones = $vista()['acciones'];
        $this->assertSame(['preparado'], array_column($acciones, 'accion'));
        $this->assertFalse($acciones[0]['habilitada']);
        $this->assertSame('Faltan 2 puntos de la revisión', $acciones[0]['motivo_deshabilitada']);
        $this->assertTrue($acciones[0]['principal']);

        $this->cumplirChecklist($cliente, $preparador);
        $this->assertTrue($vista()['acciones'][0]['habilitada']);
        $this->assertTrue($vista()['puede_aprobar']);

        $this->enEstado($cliente, EstadoExpediente::PreparadoEnTaxwise);
        $this->assertSame(['a_firma', 'presentado'], array_column($vista()['acciones'], 'accion'));
        $this->assertSame('fecha', $vista()['acciones'][1]['requiere']);

        $this->enEstado($cliente, EstadoExpediente::Presentado);
        $this->assertFalse($vista()['decisiones_abiertas'] === true);
        $this->assertSame('motivo', collect($vista()['acciones'])->firstWhere('accion', 'rechazado')['requiere']);
    }
}
