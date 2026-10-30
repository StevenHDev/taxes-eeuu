<?php

namespace Tests\Feature\Entrevista;

use App\Enums\AccionAuditoria;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\RespuestaRechazada;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F2.2: el único camino de escritura de la entrevista valida contra el
 * catálogo y el motor, cifra, guarda historial y mantiene FormaCliente.
 */
class GuardadorTest extends TestCase
{
    use RefreshDatabase;

    private RespuestaGuardador $guardador;

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        $this->guardador = app(RespuestaGuardador::class);
        $this->cliente = User::factory()->create(['role' => UserRole::Client]);
        $this->entrevista = $this->guardador->entrevistaDe($this->cliente, CanalRespuesta::Whatsapp);
    }

    private function responder(string $codigo, mixed $valor, CanalRespuesta $canal = CanalRespuesta::Portal, ?int $personaId = null): EntrevistaRespuesta
    {
        return $this->guardador->guardar($this->entrevista, new RespuestaData($codigo, $valor, $personaId), $canal, $this->cliente);
    }

    private function rechaza(string $codigo, mixed $valor, CanalRespuesta $canal = CanalRespuesta::Portal, ?int $personaId = null): void
    {
        try {
            $this->responder($codigo, $valor, $canal, $personaId);
            $this->fail("Debió rechazar {$codigo}.");
        } catch (RespuestaRechazada $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sensibles(): array
    {
        return array_combine($codigos = ['A3', 'A12', 'B4', 'C9', 'C10', 'G3', 'G4'], array_map(fn ($c) => [$c], $codigos));
    }

    #[DataProvider('sensibles')]
    public function test_por_whatsapp_nunca_se_guarda_una_pregunta_sensible(string $codigo): void
    {
        $this->responder('B1', 'casado');

        $this->rechaza($codigo, 'si', CanalRespuesta::Whatsapp);

        $this->assertSame(0, $this->entrevista->respuestas()->where('pregunta_codigo', $codigo)->count());
    }

    public function test_un_valor_de_otro_tipo_o_fuera_de_las_opciones_falla(): void
    {
        $this->rechaza('B1', 'casada');
        $this->rechaza('D1', 'tal vez');
        $this->rechaza('A6', ['usted', 'hermano']);
        $this->rechaza('A1', '   ');
        $this->rechaza('NO-EXISTE', 'si');

        $this->assertSame(0, $this->entrevista->respuestas()->count());
    }

    public function test_una_pregunta_que_no_se_ve_o_sin_su_persona_falla(): void
    {
        $this->rechaza('B2', 'si');
        $this->rechaza('A14', ['usted']);

        $this->responder('C0', 'si');
        $this->rechaza('C3', 'hijo');
        $this->rechaza('D1', 'si', personaId: $this->guardador->agregarPersona($this->entrevista)->id);
    }

    public function test_normaliza_los_valores(): void
    {
        $this->assertSame('si', $this->responder('D1', true)->valor);
        $this->assertSame(['usted', 'conyuge'], $this->responder('A6', ['conyuge', 'usted'])->valor);
        $this->assertSame(['usted'], $this->responder('A9', 'usted')->valor);
        $this->assertSame(2, $this->responder('D1.1', '2')->valor);
    }

    public function test_cada_cambio_deja_historial_con_el_valor_anterior(): void
    {
        $respuesta = $this->responder('D1', 'si', CanalRespuesta::Whatsapp);
        $this->assertSame(0, $respuesta->cambios()->count(), 'La primera respuesta no deja historial.');

        $this->responder('D1', 'si');
        $this->assertSame(0, $respuesta->cambios()->count(), 'Repetir el mismo valor no es un cambio.');

        $this->responder('D1', 'no');
        $cambio = $respuesta->cambios()->sole();

        $this->assertSame('si', $cambio->valor_anterior);
        $this->assertSame('no', $cambio->valor_nuevo);
        $this->assertSame(CanalRespuesta::Portal, $cambio->canal);
        $this->assertSame($this->cliente->id, $cambio->actor_id);
    }

    public function test_responder_una_entrevista_confirmada_la_reabre(): void
    {
        $this->entrevista->update(['estado' => EstadoEntrevista::Confirmada, 'confirmada_en' => now()]);

        $this->responder('D1', 'si');

        $this->assertSame(EstadoEntrevista::Reabierta, $this->entrevista->fresh()->estado);
    }

    public function test_el_valor_queda_cifrado_en_la_base(): void
    {
        $this->responder('A1', 'Juan Pérez');

        $crudo = DB::table('entrevista_respuestas')->where('pregunta_codigo', 'A1')->value('valor');

        $this->assertStringNotContainsString('Juan', $crudo);
        $this->assertSame('Juan Pérez', EntrevistaRespuesta::query()->where('pregunta_codigo', 'A1')->sole()->valor);
    }

    public function test_d15_declara_el_schedule_c_y_quitarlo_lo_saca_si_nadie_lo_reviso(): void
    {
        $formas = fn () => FormaCliente::query()->where('user_id', $this->cliente->id)->pluck('forma')->sort()->values()->all();

        $this->responder('D15', 'si');
        $this->assertSame([TaxForm::Form1040->value, TaxForm::ScheduleC->value], $formas());

        $this->responder('D15', 'no');
        $this->assertSame([TaxForm::Form1040->value], $formas());

        $this->responder('D15', 'si');
        FormaCliente::query()->where('forma', TaxForm::ScheduleC->value)->sole()->marcarRevisado(User::factory()->create(['role' => UserRole::Preparer]));
        $this->responder('D15', 'no');
        $this->assertContains(TaxForm::ScheduleC->value, $formas(), 'Revisada por un preparador: se queda.');
    }

    public function test_d18_k1_activa_el_documento_y_el_schedule_e(): void
    {
        $this->responder('D18', 'si');

        $this->assertContains(TaxForm::ScheduleE->value, FormaCliente::query()->where('user_id', $this->cliente->id)->pluck('forma')->all());
    }

    public function test_solo_el_administrador_cambia_la_bandera_y_queda_en_la_bitacora(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->patch(route('usuarios.entrevista-catalogo', $this->cliente), ['entrevista_catalogo' => true])->assertForbidden();
        $this->actingAs($admin)->patch(route('usuarios.entrevista-catalogo', $preparador), ['entrevista_catalogo' => true])->assertForbidden();

        // Con el consentimiento provisional (F2.4) solo pasan los de prueba.
        $this->cliente->forceFill(['es_prueba' => true])->save();
        $this->actingAs($admin)->patch(route('usuarios.entrevista-catalogo', $this->cliente), ['entrevista_catalogo' => true])->assertRedirect();

        $this->assertTrue($this->cliente->fresh()->usaEntrevistaCatalogo());
        $this->assertSame(['entrevista_catalogo'], BitacoraActividad::query()
            ->where('auditable_type', User::class)->where('auditable_id', $this->cliente->id)
            ->where('accion', AccionAuditoria::Actualizado->value)->latest('id')->first()->campos_afectados);
    }

    public function test_los_clientes_nuevos_toman_la_bandera_de_la_configuracion(): void
    {
        $this->assertFalse($this->cliente->usaEntrevistaCatalogo());

        config(['tax.entrevista_catalogo_por_defecto' => true]);

        $this->assertTrue(User::factory()->create(['role' => UserRole::Client])->usaEntrevistaCatalogo());
        $this->assertFalse(User::factory()->create(['role' => UserRole::Preparer])->fresh()->entrevista_catalogo);
    }
}
