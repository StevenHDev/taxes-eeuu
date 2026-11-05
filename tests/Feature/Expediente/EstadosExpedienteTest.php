<?php

namespace Tests\Feature\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\PuntoRevision;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\UserRole;
use App\Events\ExpedienteCambioDeEstado;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\Expediente\ExpedienteRevisionPunto;
use App\Models\User;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Expediente\DiagnosticoExpediente;
use App\Services\Expediente\EstadosExpediente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F5.1b: el estado derivado del expediente, R-02, la reapertura de la
 * revisión y la alerta tras presentar. Los eventos nunca llevan valores leídos.
 */
class EstadosExpedienteTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    private int $anio;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('local');
        $this->seed(CatalogoFiscalSeeder::class);
        $this->anio = (int) config('tax.current_tax_year');
        App::setLocale('es');
    }

    private function estado(User $cliente): EstadoExpediente
    {
        return app(EstadosExpediente::class)->recalcular($cliente, $this->anio)->estado;
    }

    private function cerrarTodo(Entrevista $e): void
    {
        foreach ($e->documentos()->vigentes()->get() as $item) {
            $item->update(['estado' => EstadoDocumento::Validado]);
        }
    }

    private function w2(Entrevista $e)
    {
        return $e->documentos()->where('documento_codigo', 'w2')->firstOrFail();
    }

    public function test_sin_entrevista_es_invitado(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $diagnostico = app(DiagnosticoExpediente::class)->para($cliente, $this->anio);

        $this->assertSame(EstadoExpediente::Invitado, $diagnostico->estado);
        $this->assertSame([], $diagnostico->faltan);
        $this->assertSame(0, $diagnostico->itemsCliente);
        $this->assertSame(EstadoExpediente::Invitado, $this->estado($cliente));
    }

    public function test_sin_consentimiento_es_invitado(): void
    {
        [$cliente] = $this->clienteConExpediente(consentimiento: false);

        $this->assertSame(EstadoExpediente::Invitado, $this->estado($cliente));
    }

    public function test_entrevista_sin_confirmar_esta_en_entrevista(): void
    {
        [$cliente] = $this->clienteConExpediente(confirmada: false);

        $this->assertSame(EstadoExpediente::EnEntrevista, $this->estado($cliente));
    }

    public function test_un_documento_con_observacion_esta_en_consulta(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        $this->w2($e)->update(['estado' => EstadoDocumento::ConObservacion, 'observacion' => 'La imagen está cortada']);

        $this->assertSame(EstadoExpediente::EnConsulta, $this->estado($cliente));
    }

    public function test_un_no_aplica_rechazado_esta_en_consulta(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        $this->w2($e)->update(['estado' => EstadoDocumento::NoAplicaRechazado, 'observacion' => 'Sí lo necesitamos']);

        $this->assertSame(EstadoExpediente::EnConsulta, $this->estado($cliente));
    }

    public function test_la_consulta_manda_sobre_los_documentos_pendientes(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        $this->w2($e)->update(['estado' => EstadoDocumento::ConObservacion, 'observacion' => 'Revisa la imagen']);
        $this->item($e, 'otro', EstadoDocumento::Pendiente)->update(['descripcion' => 'Otro banco']);

        $this->assertSame(EstadoExpediente::EnConsulta, $this->estado($cliente));
    }

    public function test_un_documento_pendiente_esta_en_recoleccion(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        $this->w2($e)->update(['estado' => EstadoDocumento::Pendiente]);

        $this->assertSame(EstadoExpediente::EnRecoleccion, $this->estado($cliente));
    }

    public function test_un_documento_leido_esta_por_validar(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        $this->w2($e)->update(['estado' => EstadoDocumento::Leido]);

        $diagnostico = app(DiagnosticoExpediente::class)->para($cliente, $this->anio);

        $this->assertSame(EstadoExpediente::PorValidar, $diagnostico->estado);
        $this->assertSame(1, $diagnostico->itemsPreparador);
        $this->assertSame(['1 documento sin validar'], $diagnostico->motivos());
        $this->assertFalse($diagnostico->r02Cumple);
        $this->assertSame('w2', $diagnostico->faltan[0]['codigo']);
        $this->assertSame('leido', $diagnostico->faltan[0]['estado']);
    }

    public function test_una_excepcion_de_revision_frena_y_una_informativa_no(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        $this->excepcion($e, 'R-07', SeveridadRegla::Informativa);

        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));

        $this->excepcion($e, 'R-03', SeveridadRegla::Revision);
        $diagnostico = app(DiagnosticoExpediente::class)->para($cliente, $this->anio);

        $this->assertSame(EstadoExpediente::PorValidar, $diagnostico->estado);
        $this->assertSame(1, $diagnostico->excepcionesQueFrenan);
        $this->assertSame(1, $diagnostico->informativas);
    }

    public function test_todo_validado_o_no_aplica_esta_listo_para_preparar(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        $this->item($e, 'otro', EstadoDocumento::NoAplica)->update(['descripcion' => 'Otro banco', 'motivo_no_aplica' => 'No tengo']);

        $diagnostico = app(DiagnosticoExpediente::class)->para($cliente, $this->anio);

        $this->assertSame(EstadoExpediente::ListoParaPreparar, $diagnostico->estado);
        $this->assertTrue($diagnostico->listo());
        $this->assertTrue($diagnostico->r02Activa);
        $this->assertTrue($diagnostico->r02Cumple);
        $this->assertSame([], $diagnostico->faltan);
        $this->assertSame('Otro banco', $diagnostico->noAplica[0]['nombre']);
        $this->assertSame('No tengo', $diagnostico->noAplica[0]['motivo']);
        $this->assertSame([], $diagnostico->motivos());
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
    }

    public function test_r02_apagada_en_el_catalogo_no_frena(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        // Sin documentos vigentes el checklist no está "completo": solo R-02 frena.
        $e->documentos()->update(['retirado_en' => now()]);

        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));
        $this->assertTrue(app(DiagnosticoExpediente::class)->para($cliente, $this->anio)->r02Activa);

        CatalogoRegla::query()->delAnio($this->anio)->where('codigo', 'R-02')->update(['implementada' => false]);
        $diagnostico = app(DiagnosticoExpediente::class)->para($cliente, $this->anio);

        $this->assertFalse($diagnostico->r02Activa);
        $this->assertTrue($diagnostico->r02Cumple);
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
    }

    public function test_desde_en_revision_se_reabre_y_borra_las_confirmaciones(): void
    {
        [$cliente, $e, $preparador] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        $expediente = app(EstadosExpediente::class)->recalcular($cliente, $this->anio);
        $expediente->update(['estado' => EstadoExpediente::EnRevision]);
        $punto = ExpedienteRevisionPunto::query()->create([
            'expediente_id' => $expediente->id, 'punto' => PuntoRevision::Identidad,
            'confirmado_por' => $preparador->id, 'confirmado_en' => now(),
        ]);

        $this->assertSame(EstadoExpediente::EnRevision, $this->estado($cliente));
        $this->assertNotNull($punto->fresh()->confirmado_en);

        $this->w2($e)->update(['estado' => EstadoDocumento::Leido]);
        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        $punto->refresh();
        $this->assertNull($punto->confirmado_en);
        $this->assertNull($punto->confirmado_por);
        $evento = ExpedienteEvento::query()->where('tipo', TipoEventoExpediente::Reabierto)->sole();
        $this->assertSame(EstadoExpediente::EnRevision, $evento->estado_anterior);
        $this->assertSame(EstadoExpediente::PorValidar, $evento->estado_nuevo);
        $this->assertNull($evento->actor_id);
    }

    public function test_desde_preparado_en_taxwise_se_reabre_con_motivo(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        app(EstadosExpediente::class)->recalcular($cliente, $this->anio)->update(['estado' => EstadoExpediente::PreparadoEnTaxwise]);
        $this->excepcion($e, 'R-04', SeveridadRegla::Bloqueante);

        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        $evento = ExpedienteEvento::query()->where('tipo', TipoEventoExpediente::Reabierto)->sole();
        $this->assertSame('1 excepción bloqueante abierta', $evento->motivo);
        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $evento->estado_anterior);
    }

    public function test_presentado_no_cambia_y_marca_la_alerta_una_vez(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        app(EstadosExpediente::class)->recalcular($cliente, $this->anio)->update(['estado' => EstadoExpediente::Presentado]);

        $this->assertSame(EstadoExpediente::Presentado, $this->estado($cliente));
        $this->assertNull(Expediente::query()->sole()->cambios_tras_presentar_en);

        $this->w2($e)->update(['estado' => EstadoDocumento::Leido]);
        $this->assertSame(EstadoExpediente::Presentado, $this->estado($cliente));
        $primera = Expediente::query()->sole()->cambios_tras_presentar_en;
        $this->assertNotNull($primera);

        $this->excepcion($e, 'R-04', SeveridadRegla::Bloqueante);
        $this->estado($cliente);

        $this->assertEquals($primera, Expediente::query()->sole()->cambios_tras_presentar_en);
        $this->assertSame(1, ExpedienteEvento::query()->where('tipo', TipoEventoExpediente::CambioTrasPresentar)->count());
        $this->assertSame(0, ExpedienteEvento::query()->where('tipo', TipoEventoExpediente::Reabierto)->count());
    }

    public function test_correr_dos_veces_no_duplica_eventos(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->w2($e)->update(['estado' => EstadoDocumento::Leido]);

        $this->estado($cliente);
        $despues = ExpedienteEvento::query()->count();
        $this->estado($cliente);
        $this->estado($cliente);

        $this->assertGreaterThan(0, $despues);
        $this->assertSame($despues, ExpedienteEvento::query()->count());
    }

    public function test_el_perfil_y_el_vencimiento(): void
    {
        [$cliente, $e] = $this->clienteConExpediente(['D1' => 'si', 'D15' => 'si', 'D9' => 'si']);
        app(RespuestaGuardador::class)->agregarPersona($e);

        $expediente = app(EstadosExpediente::class)->recalcular($cliente, $this->anio);

        $this->assertEqualsCanonicalizing(['asalariado', 'independiente', 'inversiones', 'dependientes'], $expediente->perfil);
        $this->assertSame($e->id, $expediente->entrevista_id);
        $this->assertSame('2026-04-15', $expediente->vence_en->toDateString());

        app(RespuestaGuardador::class)->guardar(
            $e->fresh(), new RespuestaData('G2', 'si'), CanalRespuesta::Portal, $cliente,
        );

        $this->assertSame('2026-10-15', app(EstadosExpediente::class)->recalcular($cliente, $this->anio)->vence_en->toDateString());
    }

    public function test_registrar_cambio_dispara_el_evento(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $expediente = app(EstadosExpediente::class)->recalcular($cliente, $this->anio);
        Event::fake([ExpedienteCambioDeEstado::class]);

        app(EstadosExpediente::class)->registrarCambio($expediente, EstadoExpediente::EnRevision, $preparador, motivo: 'Empezó la revisión');
        app(EstadosExpediente::class)->registrarCambio($expediente->fresh(), EstadoExpediente::EnRevision, $preparador);

        Event::assertDispatchedTimes(ExpedienteCambioDeEstado::class, 1);
        Event::assertDispatched(ExpedienteCambioDeEstado::class, fn ($ev) => $ev->nuevo === EstadoExpediente::EnRevision
            && $ev->actor->is($preparador)
            && $ev->expediente->is($expediente));
        $this->assertSame(EstadoExpediente::EnRevision, $expediente->fresh()->estado);
        $this->assertSame($preparador->id, ExpedienteEvento::query()->where('motivo', 'Empezó la revisión')->sole()->actor_id);
    }

    public function test_los_eventos_no_guardan_valores_sensibles(): void
    {
        [$cliente, $e] = $this->clienteConExpediente();
        $this->cerrarTodo($e);
        app(EstadosExpediente::class)->recalcular($cliente, $this->anio)->update(['estado' => EstadoExpediente::EnRevision]);

        $item = $this->w2($e);
        $this->datoLeido($item, 'a', '123-45-6789');
        $item->update(['estado' => EstadoDocumento::Leido]);
        $excepcion = $this->excepcion($e, 'R-01', SeveridadRegla::Bloqueante, $item);
        $excepcion->update(['detalle' => ['casilla' => 'a'], 'estado' => EstadoExcepcion::Abierta]);
        $this->estado($cliente);

        $this->assertGreaterThan(0, ExpedienteEvento::query()->count());

        foreach (ExpedienteEvento::query()->get() as $evento) {
            $this->assertStringNotContainsString('123-45-6789', json_encode($evento->toArray()));
            $this->assertStringNotContainsString('123456789', json_encode($evento->toArray()));
        }
    }
}
