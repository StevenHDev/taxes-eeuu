<?php

namespace Tests\Feature\Expediente;

use App\Enums\AccionAuditoria;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteDatoSinDocumento;
use App\Models\Expediente\ExpedienteDatoSinDocumentoCambio;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Expediente\DatosSinDocumento;
use App\Services\Lectura\Reglas\R15SinDocumento;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F6 6.5: el respaldo manual sin documento (último recurso), del lado del
 * servidor. El valor y el motivo nunca salen en logs, bitácora, excepciones
 * ni respuestas de error.
 */
class DatosSinDocumentoTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    private const CLAVE = 'comprobantes-pagos:monto';

    /** Un monto y un motivo fáciles de buscar en cualquier salida. */
    private const VALOR = '4321.87';

    private const MOTIVO = 'Pago por ventanilla sin comprobante QX7';

    private int $anio;

    /** @var list<string> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        $this->anio = (int) config('tax.current_tax_year');
        App::setLocale('es');

        Event::listen(MessageLogged::class, function (MessageLogged $m) {
            $this->logs[] = $m->message.' '.json_encode($m->context);
        });
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function anotar(User $actor, User $cliente, array $extra = []): TestResponse
    {
        return $this->actingAs($actor)->post(route('clientes.borrador.sin-documento.store', $cliente), [
            'tax_year' => $this->anio,
            'clave' => self::CLAVE,
            'valor' => self::VALOR,
            'motivo' => self::MOTIVO,
            'fuente' => 'cliente_dijo',
            'fuente_fecha' => now()->subDay()->toDateString(),
            'fuente_canal' => 'llamada',
            ...$extra,
        ]);
    }

    private function unico(): ExpedienteDatoSinDocumento
    {
        return ExpedienteDatoSinDocumento::query()->sole();
    }

    private function assertSinValorNiMotivo(string $texto): void
    {
        $this->assertStringNotContainsString(self::VALOR, $texto);
        $this->assertStringNotContainsString('4,321', $texto);
        $this->assertStringNotContainsString(self::MOTIVO, $texto);
    }

    public function test_anotar_guarda_el_valor_cifrado_con_su_fuente_y_su_historial(): void
    {
        [$cliente, , $preparador] = $this->listo();

        $this->anotar($preparador, $cliente, ['valor' => '$4,321.87'])->assertRedirect()->assertSessionHasNoErrors();

        $dato = $this->unico();
        $this->assertSame(self::VALOR, $dato->valor);
        $this->assertSame(self::MOTIVO, $dato->motivo);
        $this->assertSame('cliente_dijo', $dato->fuente);
        $this->assertSame('llamada', $dato->fuente_canal);
        $this->assertSame($preparador->id, $dato->anotado_por);
        $this->assertSame($this->expediente($cliente)->id, $dato->expediente_id);

        $crudo = DB::table('expediente_datos_sin_documento')->value('valor');
        $this->assertStringNotContainsString(self::VALOR, $crudo);

        $cambio = $dato->cambios()->sole();
        $this->assertSame(ExpedienteDatoSinDocumentoCambio::ANOTADO, $cambio->accion);
        $this->assertNull($cambio->valor_anterior);
        $this->assertSame(self::VALOR, $cambio->valor_nuevo);
        $this->assertStringNotContainsString(self::VALOR, DB::table('expediente_dato_sin_documento_cambios')->value('valor_nuevo'));

        $this->assertArrayNotHasKey('valor', $dato->toArray());
    }

    public function test_anotar_exige_motivo(): void
    {
        [$cliente, , $preparador] = $this->listo();

        $this->anotar($preparador, $cliente, ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->assertSame('Escribe por qué no hay documento.', session('errors')->first('motivo'));

        $this->anotar($preparador, $cliente, ['motivo' => '   '])->assertSessionHasErrors('motivo');
        $this->assertSame(0, ExpedienteDatoSinDocumento::query()->count());
    }

    public function test_si_el_cliente_lo_dijo_pide_la_fecha_y_el_canal(): void
    {
        [$cliente, , $preparador] = $this->listo();

        $this->anotar($preparador, $cliente, ['fuente_fecha' => null, 'fuente_canal' => null])->assertSessionHasErrors(['fuente_fecha', 'fuente_canal']);
        $this->anotar($preparador, $cliente, ['fuente_fecha' => now()->addDay()->toDateString()])->assertSessionHasErrors('fuente_fecha');

        $this->anotar($preparador, $cliente, ['fuente' => 'otro', 'fuente_fecha' => null, 'fuente_canal' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->unico()->fuente_fecha);
    }

    public function test_no_se_puede_anotar_si_el_documento_tiene_un_archivo_leido(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $this->datoLeido($this->item($e, 'comprobantes-pagos', EstadoDocumento::Leido), 'monto', '500.00');

        $this->anotar($preparador, $cliente)->assertSessionHasErrors('clave');
        $this->assertSame('Este dato ya sale del documento; corrígelo en el visor.', session('errors')->first('clave'));
        $this->assertSame(0, ExpedienteDatoSinDocumento::query()->count());
    }

    public function test_la_clave_tiene_que_existir_en_el_catalogo_y_ser_un_monto(): void
    {
        [$cliente, , $preparador] = $this->listo();

        foreach (['no-existe:monto', 'comprobantes-pagos:no-existe', 'comprobantes-pagos:fecha-pago', 'w2:b', 'comprobantes-pagos', 'otro:monto', 'registros-ingresos-gastos:total-depositos', 'registros-ingresos-gastos:99'] as $clave) {
            $this->anotar($preparador, $cliente, ['clave' => $clave])->assertSessionHasErrors('clave');
        }

        $this->assertSame(0, ExpedienteDatoSinDocumento::query()->count());

        $this->anotar($preparador, $cliente, ['clave' => 'registros-ingresos-gastos:1-sin-formulario'])->assertSessionHasNoErrors();
        $this->anotar($preparador, $cliente, ['clave' => 'w2:1'])->assertSessionHasNoErrors();
        $this->assertSame(2, ExpedienteDatoSinDocumento::query()->count());
    }

    public function test_el_valor_tiene_que_ser_un_monto(): void
    {
        [$cliente, , $preparador] = $this->listo();

        foreach (['abc', '-10', '10.555', '1e5', ''] as $valor) {
            $this->anotar($preparador, $cliente, ['valor' => $valor])->assertSessionHasErrors('valor');
        }

        $this->assertSame(0, ExpedienteDatoSinDocumento::query()->count());
        $this->assertSame('1500.50', DatosSinDocumento::monto('$1,500.5'));
        $this->assertSame('7.00', DatosSinDocumento::monto('7'));
    }

    public function test_no_se_anota_dos_veces_la_misma_clave_para_la_misma_persona(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $persona = app(RespuestaGuardador::class)->agregarPersona($e);

        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $this->anotar($preparador, $cliente)->assertSessionHasErrors('clave');
        $this->anotar($preparador, $cliente, ['clave' => 'w2:1'])->assertSessionHasNoErrors();
        $this->anotar($preparador, $cliente, ['clave' => 'w2:1', 'persona_id' => $persona->id])->assertSessionHasNoErrors();

        $this->assertSame(3, ExpedienteDatoSinDocumento::query()->count());
    }

    public function test_el_documento_leido_despues_reemplaza_al_manual_y_vigentes_ya_no_lo_devuelve(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $servicio = app(DatosSinDocumento::class);
        $expediente = $this->expediente($cliente);

        $this->assertSame([$this->unico()->id], $servicio->vigentes($expediente)->modelKeys());

        $this->datoLeido($this->item($e, 'comprobantes-pagos', EstadoDocumento::Leido), 'monto', '4000.00');

        $this->assertSame([], $servicio->vigentes($expediente)->modelKeys());

        $dato = $this->unico();
        $this->assertNotNull($dato->reemplazado_en);
        $this->assertSame(self::VALOR, $dato->valor);
        $reemplazo = $dato->cambios()->where('accion', ExpedienteDatoSinDocumentoCambio::REEMPLAZADO)->sole();
        $this->assertNull($reemplazo->actor_id);
        $this->assertSame(self::VALOR, $reemplazo->valor_anterior);

        // Correr de nuevo no duplica el historial.
        $servicio->vigentes($expediente);
        $this->assertSame(2, $dato->cambios()->count());
    }

    public function test_cambiar_deja_el_historial_con_el_valor_anterior_y_el_nuevo(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $dato = $this->unico();

        $this->actingAs($preparador)->put(route('clientes.borrador.sin-documento.update', [$cliente, $dato]), ['valor' => '5000', 'motivo' => ''])
            ->assertSessionHasErrors('motivo');
        $this->actingAs($preparador)->put(route('clientes.borrador.sin-documento.update', [$cliente, $dato]), ['valor' => '5000', 'motivo' => 'El cliente corrigió la cifra'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $dato->refresh();
        $this->assertSame('5000.00', $dato->valor);
        $this->assertSame(self::MOTIVO, $dato->motivo);

        $cambio = $dato->cambios()->where('accion', ExpedienteDatoSinDocumentoCambio::CAMBIADO)->sole();
        $this->assertSame(self::VALOR, $cambio->valor_anterior);
        $this->assertSame('5000.00', $cambio->valor_nuevo);
        $this->assertSame('El cliente corrigió la cifra', $cambio->motivo);
        $this->assertSame($preparador->id, $cambio->actor_id);
    }

    public function test_retirar_lo_deja_de_contar_sin_borrarlo(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $dato = $this->unico();

        $this->actingAs($preparador)->delete(route('clientes.borrador.sin-documento.destroy', [$cliente, $dato]), ['motivo' => 'Llegó el comprobante'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $dato->refresh();
        $this->assertNotNull($dato->retirado_en);
        $this->assertSame($preparador->id, $dato->retirado_por);
        $this->assertSame([], app(DatosSinDocumento::class)->vigentes($this->expediente($cliente))->modelKeys());

        // Uno retirado ya no se cambia; y se puede volver a anotar la misma clave.
        $this->actingAs($preparador)->put(route('clientes.borrador.sin-documento.update', [$cliente, $dato]), ['valor' => '1', 'motivo' => 'x'])
            ->assertSessionHasErrors('valor');
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $this->assertSame(2, ExpedienteDatoSinDocumento::query()->count());
    }

    public function test_anotar_en_preparado_reabre_la_revision_aprobada(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->expediente($cliente)->update(['estado' => EstadoExpediente::PreparadoEnTaxwise, 'estado_desde' => now()]);

        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();

        $expediente = $this->expediente($cliente);
        $this->assertSame(EstadoExpediente::EnRevision, $expediente->estado);

        $reabierto = $expediente->eventos()->where('tipo', TipoEventoExpediente::Reabierto)->sole();
        $this->assertSame('Cambió un dato anotado sin documento después de preparar', $reabierto->motivo);
        $this->assertSame($preparador->id, $reabierto->actor_id);
        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $reabierto->estado_anterior);
    }

    public function test_cambiar_en_esperando_firma_tambien_reabre_y_en_revision_no_cambia_el_estado(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->expediente($cliente)->update(['estado' => EstadoExpediente::EnRevision, 'estado_desde' => now()]);
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::EnRevision, $this->expediente($cliente)->estado);
        $this->assertSame(0, $this->expediente($cliente)->eventos()->where('tipo', TipoEventoExpediente::Reabierto)->count());

        $this->expediente($cliente)->update(['estado' => EstadoExpediente::EsperandoFirma]);
        $this->actingAs($preparador)->put(route('clientes.borrador.sin-documento.update', [$cliente, $this->unico()]), ['valor' => '10', 'motivo' => 'Ajuste'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoExpediente::EnRevision, $this->expediente($cliente)->estado);
    }

    public function test_despues_de_presentar_no_se_anota(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->expediente($cliente)->update(['estado' => EstadoExpediente::Presentado, 'estado_desde' => now()]);

        $this->anotar($preparador, $cliente)->assertSessionHasErrors('clave');
        $this->assertSame(0, ExpedienteDatoSinDocumento::query()->count());
    }

    public function test_r15_da_un_hallazgo_por_dato_vigente_sin_valor_ni_motivo(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $this->anotar($preparador, $cliente, ['clave' => 'registros-ingresos-gastos:1-sin-formulario'])->assertSessionHasNoErrors();

        $regla = app(R15SinDocumento::class);
        $this->assertSame('R-15', $regla->codigo());

        $hallazgos = $regla->evaluar($e, collect());
        $this->assertCount(2, $hallazgos);

        $primero = ExpedienteDatoSinDocumento::query()->where('clave', self::CLAVE)->sole();
        $this->assertSame("R-15#dato:{$primero->id}", $hallazgos[0]->clave);
        $this->assertSame(['dato_sin_documento_id' => $primero->id, 'clave' => self::CLAVE, 'persona_id' => null, 'anotado_por' => $preparador->id], $hallazgos[0]->detalle);
        $this->assertNull($hallazgos[0]->item);

        foreach ($hallazgos as $h) {
            $this->assertSinValorNiMotivo(json_encode($h->detalle));
        }

        // Con el documento leído, ese dato ya no da hallazgo.
        $this->datoLeido($this->item($e, 'comprobantes-pagos', EstadoDocumento::Leido), 'monto', '4000.00');
        $this->assertCount(1, $regla->evaluar($e, collect()));
    }

    public function test_r15_sin_expediente_no_da_nada(): void
    {
        [, $e] = $this->listo();
        Expediente::query()->delete();

        $this->assertSame([], app(R15SinDocumento::class)->evaluar($e, collect()));
    }

    public function test_el_cliente_y_otro_preparador_reciben_403(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $dato = $this->unico();
        $otro = User::factory()->create(['role' => UserRole::Preparer]);

        foreach ([$cliente, $otro] as $quien) {
            $this->anotar($quien, $cliente, ['clave' => 'w2:1'])->assertForbidden();
            $this->actingAs($quien)->put(route('clientes.borrador.sin-documento.update', [$cliente, $dato]), ['valor' => '1', 'motivo' => 'x'])->assertForbidden();
            $this->actingAs($quien)->delete(route('clientes.borrador.sin-documento.destroy', [$cliente, $dato]), ['motivo' => 'x'])->assertForbidden();
        }

        $this->assertSame(1, ExpedienteDatoSinDocumento::query()->count());
        $this->assertSame(self::VALOR, $dato->fresh()->valor);
    }

    public function test_el_administrador_puede_anotar(): void
    {
        [$cliente] = $this->listo();
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->anotar($admin, $cliente)->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $this->unico()->anotado_por);
    }

    public function test_una_persona_o_un_dato_de_otro_cliente_da_404(): void
    {
        [$cliente, , $preparador] = $this->listo();
        [$otroCliente, $otraEntrevista] = $this->clienteConExpediente(['D1' => 'si', 'G5' => 'no'], preparador: $preparador);
        $ajena = app(RespuestaGuardador::class)->agregarPersona($otraEntrevista);

        $this->anotar($preparador, $cliente, ['persona_id' => $ajena->id])->assertNotFound();

        $this->anotar($preparador, $otroCliente)->assertSessionHasNoErrors();
        $ajeno = $this->unico();

        $this->actingAs($preparador)->put(route('clientes.borrador.sin-documento.update', [$cliente, $ajeno]), ['valor' => '1', 'motivo' => 'x'])->assertNotFound();
        $this->actingAs($preparador)->delete(route('clientes.borrador.sin-documento.destroy', [$cliente, $ajeno]), ['motivo' => 'x'])->assertNotFound();
    }

    public function test_la_bitacora_registra_la_clave_sin_el_valor_ni_el_motivo(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();
        $dato = $this->unico();
        $this->actingAs($preparador)->put(route('clientes.borrador.sin-documento.update', [$cliente, $dato]), ['valor' => '99', 'motivo' => self::MOTIVO])->assertSessionHasNoErrors();
        $this->actingAs($preparador)->delete(route('clientes.borrador.sin-documento.destroy', [$cliente, $dato]), ['motivo' => self::MOTIVO])->assertSessionHasNoErrors();

        $filas = BitacoraActividad::query()->where('auditable_type', ExpedienteDatoSinDocumento::class)->orderBy('id')->get();

        $this->assertSame([
            "Anotó un dato sin documento: comprobantes-pagos:monto (cliente #{$cliente->id})",
            "Cambió un dato sin documento: comprobantes-pagos:monto (cliente #{$cliente->id})",
            "Retiró un dato sin documento: comprobantes-pagos:monto (cliente #{$cliente->id})",
        ], $filas->pluck('etiqueta')->all());
        $this->assertTrue($filas->every(fn (BitacoraActividad $b) => $b->accion === AccionAuditoria::Actualizado && $b->actor_id === $preparador->id));

        $this->assertSinValorNiMotivo(BitacoraActividad::query()->get()->toJson());
        $this->assertSinValorNiMotivo(json_encode(DB::table('expediente_eventos')->get()));
        $this->assertSinValorNiMotivo(json_encode(Excepcion::query()->get()));
    }

    public function test_el_valor_y_el_motivo_nunca_salen_en_logs_ni_en_respuestas_de_error(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $this->anotar($preparador, $cliente)->assertSessionHasNoErrors();

        // Errores: documento leído, clave repetida, persona inválida, después de presentar.
        $this->anotar($preparador, $cliente)->assertSessionHasErrors('clave');
        $this->assertSinValorNiMotivo(json_encode(session('errors')->getBag('default')->toArray()));

        $this->datoLeido($this->item($e, 'comprobantes-pagos', EstadoDocumento::Leido), 'monto', '1.00');
        $this->anotar($preparador, $cliente, ['clave' => self::CLAVE])->assertSessionHasErrors('clave');
        $this->assertSinValorNiMotivo(json_encode(session('errors')->getBag('default')->toArray()));

        $json = $this->actingAs($preparador)->postJson(route('clientes.borrador.sin-documento.store', $cliente), [
            'tax_year' => $this->anio, 'clave' => 'no-existe:monto', 'valor' => self::VALOR, 'motivo' => self::MOTIVO, 'fuente' => 'otro',
        ])->assertUnprocessable();
        $this->assertSinValorNiMotivo($json->getContent());

        $this->expediente($cliente)->update(['estado' => EstadoExpediente::Presentado]);
        $json = $this->actingAs($preparador)->postJson(route('clientes.borrador.sin-documento.store', $cliente), [
            'tax_year' => $this->anio, 'clave' => 'w2:1', 'valor' => self::VALOR, 'motivo' => self::MOTIVO, 'fuente' => 'otro',
        ])->assertUnprocessable();
        $this->assertSinValorNiMotivo($json->getContent());

        $this->assertSinValorNiMotivo(implode("\n", $this->logs));
    }

    public function test_los_mensajes_salen_en_ingles(): void
    {
        [$cliente, $e, $preparador] = $this->listo();
        $this->datoLeido($this->item($e, 'comprobantes-pagos', EstadoDocumento::Leido), 'monto', '1.00');
        App::setLocale('en');

        $this->anotar($preparador, $cliente)->assertSessionHasErrors('clave');
        $this->assertSame('This amount already comes from the document; correct it in the viewer.', session('errors')->first('clave'));
    }
}
