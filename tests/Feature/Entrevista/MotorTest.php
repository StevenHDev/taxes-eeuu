<?php

namespace Tests\Feature\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\User;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\SituacionEntrevista;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2.2: el motor único decide qué preguntas se ven y qué se activa, sobre
 * el catálogo real de 2025.
 */
class MotorTest extends TestCase
{
    use RefreshDatabase;

    private RespuestaGuardador $guardador;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        $this->guardador = app(RespuestaGuardador::class);
        $this->entrevista = $this->guardador->entrevistaDe(User::factory()->create(['role' => UserRole::Client]), CanalRespuesta::Portal);
    }

    private function responder(string $codigo, mixed $valor, ?EntrevistaPersona $persona = null): void
    {
        $this->guardador->guardar($this->entrevista, new RespuestaData($codigo, $valor, $persona?->id), CanalRespuesta::Portal, null);
    }

    private function situacion(): SituacionEntrevista
    {
        return app(MotorEntrevista::class)->situacion($this->entrevista->fresh());
    }

    public function test_la_entrevista_es_del_anio_en_curso_y_empieza_con_las_preguntas_de_entrada(): void
    {
        $this->assertSame(config('tax.current_tax_year'), $this->entrevista->tax_year);

        $situacion = $this->situacion();

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], array_keys($situacion->pantallas));
        $this->assertTrue($situacion->esVisible('B1'));
        $this->assertTrue($situacion->esVisible('C0'));
        $this->assertFalse($situacion->esVisible('A14'), 'no_se_pregunta');
        $this->assertFalse($situacion->esVisible('B2'));
        $this->assertFalse($situacion->esVisible('A7'));
        $this->assertSame([['codigo' => '1040', 'activado_por' => []]], $situacion->formularios, 'El 1040 va siempre.');
    }

    public function test_b2_a_b4_solo_con_casado_y_a7_solo_con_a6_no(): void
    {
        $this->responder('B1', 'casado');
        $this->responder('A6', 'no');

        $situacion = $this->situacion();
        $this->assertTrue($situacion->esVisible('B2'));
        $this->assertTrue($situacion->esVisible('B4'));
        $this->assertFalse($situacion->esVisible('B6'));
        $this->assertTrue($situacion->esVisible('A7'));

        $this->responder('A6', ['usted']);
        $this->assertFalse($this->situacion()->esVisible('A7'));
    }

    public function test_cambiar_b1_conserva_b2_a_b4_pero_los_saca_y_volver_a_casado_los_recupera(): void
    {
        $this->responder('B1', 'casado');
        $this->responder('B2', 'si');

        $this->responder('B1', 'nunca_casado');
        $situacion = $this->situacion();
        $this->assertFalse($situacion->esVisible('B2'));
        $this->assertArrayNotHasKey('B2', $situacion->respuestas);
        $this->assertSame(1, $this->entrevista->respuestas()->where('pregunta_codigo', 'B2')->count(), 'La respuesta no se borra.');

        $this->responder('B1', 'casado');
        $this->assertSame('si', $this->situacion()->respuestas['B2']);
    }

    public function test_c0_no_salta_todo_el_bloque_c(): void
    {
        $persona = $this->guardador->agregarPersona($this->entrevista);

        $this->responder('C0', 'no');
        $this->assertFalse($this->situacion()->esVisible('C3', $persona->id));

        $this->responder('C0', 'si');
        $this->assertTrue($this->situacion()->esVisible('C3', $persona->id));
        $this->assertFalse($this->situacion()->esVisible('C3'), 'Las del bloque C solo se ven por persona.');
    }

    public function test_c12_a_c14_se_evaluan_dentro_de_cada_persona(): void
    {
        $this->responder('C0', 'si');
        $hijo = $this->guardador->agregarPersona($this->entrevista);
        $padre = $this->guardador->agregarPersona($this->entrevista);
        $hijoCorto = $this->guardador->agregarPersona($this->entrevista);

        $this->responder('C3', 'hijo', $hijo);
        $this->responder('C4', 12, $hijo);
        $this->responder('C3', 'padre', $padre);
        $this->responder('C4', 12, $padre);
        $this->responder('C3', 'hijo', $hijoCorto);
        $this->responder('C4', 5, $hijoCorto);

        $situacion = $this->situacion();
        $this->assertFalse($situacion->esVisible('C12', $hijo->id));
        $this->assertTrue($situacion->esVisible('C12', $padre->id));
        $this->assertTrue($situacion->esVisible('C14', $hijoCorto->id));
    }

    public function test_d1_si_abre_d1_1_y_activa_el_w2(): void
    {
        $this->responder('D1', 'si');
        $situacion = $this->situacion();

        $this->assertTrue($situacion->esVisible('D1.1'));
        $this->assertSame(['D1'], $situacion->activadoPor('w2'));

        $this->responder('D1', 'no');
        $situacion = $this->situacion();
        $this->assertFalse($situacion->esVisible('D1.1'));
        $this->assertFalse($situacion->tieneDocumento('w2'));
    }

    public function test_quitar_una_persona_saca_lo_que_activaba(): void
    {
        $this->responder('C0', 'si');
        $persona = $this->guardador->agregarPersona($this->entrevista);
        $this->responder('C9', 'ssn_valido', $persona);
        $this->assertTrue($this->situacion()->tieneFormulario('sch-8812'));

        $this->guardador->quitarPersona($persona);

        $this->assertFalse($this->situacion()->tieneFormulario('sch-8812'));
        $this->assertSoftDeleted($persona);
    }

    public function test_lo_sensible_sin_responder_queda_para_el_portal(): void
    {
        $falta = collect($this->situacion()->faltaEnPortal())->pluck('codigo');

        $this->assertContains('A3', $falta);
        $this->assertNotContains('B4.3', $falta, 'El SSN del cónyuge (B4.3) no se ve hasta que B1 sea casado.');
    }
}
