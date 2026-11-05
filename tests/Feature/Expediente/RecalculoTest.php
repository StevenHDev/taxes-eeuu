<?php

namespace Tests\Feature\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Lectura\EstadoExcepcion;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Expediente\DiagnosticoExpediente;
use App\Services\Expediente\EstadosExpediente;
use App\Services\Lectura\ColaExcepciones;
use App\Services\Lectura\Reglas\MotorReglas;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F5.1b: el estado del expediente se recalcula solo cuando algo cambia, sin
 * que nadie llame a `recalcular` (observer y MotorReglas), una vez por
 * cliente y año dentro de una transacción.
 */
class RecalculoTest extends TestCase
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
     * Un cliente con todo cerrado menos el W-2, que queda en el estado pedido.
     *
     * @return array{0: User, 1: Entrevista, 2: User}
     */
    private function listo(EstadoDocumento $w2 = EstadoDocumento::Validado): array
    {
        [$cliente, $e, $preparador] = $this->clienteConExpediente();

        foreach ($e->documentos()->vigentes()->get() as $item) {
            $item->update(['estado' => EstadoDocumento::Validado]);
        }

        $item = $e->documentos()->where('documento_codigo', 'w2')->firstOrFail();
        $this->archivo($item);
        $item->update(['estado' => $w2]);

        return [$cliente, $e, $preparador];
    }

    private function estado(User $cliente): ?EstadoExpediente
    {
        return Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $this->anio)->first()?->estado;
    }

    public function test_validar_un_documento_recalcula_el_estado(): void
    {
        [$cliente, $e, $preparador] = $this->listo(EstadoDocumento::Leido);
        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        app(ChecklistDocumentos::class)->validar($e->documentos()->where('documento_codigo', 'w2')->firstOrFail(), $preparador);

        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
    }

    public function test_observar_un_documento_lo_pasa_a_en_consulta(): void
    {
        [$cliente, $e, $preparador] = $this->listo(EstadoDocumento::Leido);

        app(ChecklistDocumentos::class)->observar($e->documentos()->where('documento_codigo', 'w2')->firstOrFail(), 'La imagen está cortada', $preparador);

        $this->assertSame(EstadoExpediente::EnConsulta, $this->estado($cliente));
    }

    public function test_marcar_no_aplica_recalcula_el_estado(): void
    {
        [$cliente, $e] = $this->listo(EstadoDocumento::Pendiente);
        $this->assertSame(EstadoExpediente::EnRecoleccion, $this->estado($cliente));

        app(ChecklistDocumentos::class)->marcarNoAplica($e->documentos()->where('documento_codigo', 'w2')->firstOrFail(), 'No trabajé este año');

        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
    }

    public function test_subir_un_archivo_recalcula_el_estado(): void
    {
        [$cliente, $e] = $this->listo(EstadoDocumento::Pendiente);
        $this->assertSame(EstadoExpediente::EnRecoleccion, $this->estado($cliente));

        app(DocumentosEntrevista::class)->subir(
            $e->documentos()->where('documento_codigo', 'w2')->firstOrFail(),
            UploadedFile::fake()->create('w2.pdf', 10, 'application/pdf'),
        );

        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));
    }

    public function test_aceptar_una_excepcion_recalcula_el_estado(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $excepcion = $this->excepcion($e, 'R-04', SeveridadRegla::Bloqueante);
        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        app(ColaExcepciones::class)->aceptar($excepcion, 'Revisado con el cliente', $preparador);

        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
    }

    public function test_consultar_una_excepcion_recalcula_el_estado(): void
    {
        [$cliente, $e, $preparador] = $this->listo(EstadoDocumento::Leido);
        $item = $e->documentos()->where('documento_codigo', 'w2')->firstOrFail();
        $excepcion = $this->excepcion($e, 'R-04', SeveridadRegla::Bloqueante, $item);
        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        app(ColaExcepciones::class)->consultar($excepcion, '¿Es correcto este monto?', $preparador);

        $this->assertSame(EstadoExpediente::EnConsulta, $this->estado($cliente));
    }

    public function test_cerrar_una_excepcion_con_el_motor_recalcula_el_estado(): void
    {
        [$cliente, $e] = $this->listo();
        $excepcion = $this->excepcion($e, 'R-04', SeveridadRegla::Bloqueante);
        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        // Sin datos que la sostengan, el motor la pasa a corregida con un update masivo.
        app(MotorReglas::class)->correr($e);

        $this->assertSame(EstadoExcepcion::Corregida, $excepcion->fresh()->estado);
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
    }

    public function test_confirmar_la_entrevista_recalcula_el_estado(): void
    {
        [$cliente, $e] = $this->listo();
        $e->update(['estado' => EstadoEntrevista::Reabierta]);
        $this->assertSame(EstadoExpediente::EnEntrevista, $this->estado($cliente));

        $e->update(['estado' => EstadoEntrevista::Confirmada, 'confirmada_en' => now()]);

        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
    }

    public function test_firmar_el_consentimiento_recalcula_el_estado(): void
    {
        [$cliente, $e] = $this->clienteConExpediente(consentimiento: false);
        foreach ($e->documentos()->vigentes()->get() as $item) {
            $item->update(['estado' => EstadoDocumento::Validado]);
        }
        $this->assertSame(EstadoExpediente::Invitado, $this->estado($cliente));

        app(ConsentimientosCargador::class)->cargar();
        app(Consentimientos::class)->firmar($cliente, $this->anio, 1, 'Cliente de Prueba', null, null);

        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
    }

    public function test_dentro_de_una_transaccion_con_varios_cambios_recalcula_una_vez(): void
    {
        [$cliente, $e] = $this->listo();
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));

        $contador = new class(app(DiagnosticoExpediente::class), app(MotorEntrevista::class)) extends EstadosExpediente
        {
            public int $llamadas = 0;

            public function recalcular(User $cliente, int $taxYear): Expediente
            {
                $this->llamadas++;

                return parent::recalcular($cliente, $taxYear);
            }
        };
        $this->app->instance(EstadosExpediente::class, $contador);

        $item = $e->documentos()->where('documento_codigo', 'w2')->firstOrFail();

        DB::transaction(function () use ($item, $e) {
            $item->update(['estado' => EstadoDocumento::Leido]);
            $this->excepcion($e, 'R-03', SeveridadRegla::Revision);
            $item->update(['estado' => EstadoDocumento::ConObservacion, 'observacion' => 'Revisa']);
        });

        $this->assertSame(1, $contador->llamadas);
        $this->assertSame(EstadoExpediente::EnConsulta, $this->estado($cliente));
    }

    public function test_el_comando_con_dry_run_no_escribe_y_no_imprime_el_nombre(): void
    {
        [$cliente] = $this->listo();
        Expediente::query()->delete();

        $this->assertSame(0, Artisan::call('expedientes:recalcular', ['--dry-run' => true, '--anio' => $this->anio]));
        $salida = Artisan::output();

        $this->assertSame(0, Expediente::query()->count());
        $this->assertStringContainsString('listo_para_preparar', $salida);
        $this->assertStringNotContainsString($cliente->name, $salida);
        $this->assertStringNotContainsString($cliente->email, $salida);

        $this->assertSame(0, Artisan::call('expedientes:recalcular', ['--anio' => $this->anio]));

        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));
        $this->assertStringNotContainsString($cliente->name, Artisan::output());
    }

    public function test_el_comando_con_solo_prueba_ignora_a_los_clientes_reales(): void
    {
        [$cliente] = $this->listo();
        Expediente::query()->delete();

        Artisan::call('expedientes:recalcular', ['--solo-prueba' => true]);
        $this->assertSame(0, Expediente::query()->count());

        $cliente->forceFill(['es_prueba' => true])->save();
        Artisan::call('expedientes:recalcular', ['--solo-prueba' => true]);
        $this->assertSame(1, Expediente::query()->count());
    }

    public function test_si_el_recalculo_falla_lo_que_hacia_el_cliente_sigue(): void
    {
        $this->partialMock(EstadosExpediente::class, fn ($mock) => $mock->shouldReceive('recalcular')->andThrow(new \RuntimeException('falla de prueba')));
        $this->app->forgetInstance(ExceptionHandler::class);
        $manejador = \Mockery::mock(ExceptionHandler::class);
        $manejador->shouldReceive('report')->atLeast()->once();
        $this->app->instance(ExceptionHandler::class, $manejador);

        [$cliente, $entrevista] = $this->clienteConExpediente(['D1' => 'si'], confirmada: false);

        $this->assertSame('si', $entrevista->respuestas()->where('pregunta_codigo', 'D1')->first()->valor, 'La respuesta se guardó aunque el recálculo falló.');
    }
}
