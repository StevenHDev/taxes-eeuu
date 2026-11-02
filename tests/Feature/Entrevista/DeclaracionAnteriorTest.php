<?php

namespace Tests\Feature\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\User;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\VistaEntrevistaPreparador;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3.8: un "no" en G5 ("¿Tienes la declaración del año pasado?") pregunta
 * por qué. Solo "no presenté algunos años" pregunta cuáles y le avisa al
 * preparador en la ficha.
 */
class DeclaracionAnteriorTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        $this->cliente = User::factory()->create(['role' => UserRole::Client]);
        $this->entrevista = app(RespuestaGuardador::class)->entrevistaDe($this->cliente, CanalRespuesta::Portal);
    }

    private function responder(string $codigo, mixed $valor): void
    {
        app(RespuestaGuardador::class)->guardar($this->entrevista->fresh(), new RespuestaData($codigo, $valor), CanalRespuesta::Portal, $this->cliente);
    }

    /**
     * @return list<string> las preguntas visibles de la pantalla 8
     */
    private function visibles(): array
    {
        $pantalla = collect(app(MotorEntrevista::class)->situacion($this->entrevista->fresh())->pantallas)->firstWhere('numero', 8);

        return array_column($pantalla['preguntas'], 'codigo');
    }

    /**
     * @return list<array{tipo: string, detalle: string|null}>
     */
    private function avisos(): array
    {
        return app(VistaEntrevistaPreparador::class)->para($this->cliente, $this->entrevista->tax_year, false)['avisos'];
    }

    public function test_con_la_declaracion_del_ano_pasado_no_se_pregunta_nada_mas_y_se_pide_la_copia(): void
    {
        $this->responder('G5', 'si');

        $this->assertNotContains('G5.1', $this->visibles());
        $this->assertNotContains('G5.2', $this->visibles());
        $this->assertTrue(app(MotorEntrevista::class)->situacion($this->entrevista->fresh())->tieneDocumento('declaracion-anterior'));
        $this->assertSame([], $this->avisos());
    }

    public function test_sin_la_declaracion_se_pregunta_por_que_y_solo_los_anos_sin_declarar_abren_la_siguiente(): void
    {
        $this->responder('G5', 'no');
        $this->assertContains('G5.1', $this->visibles());
        $this->assertNotContains('G5.2', $this->visibles());

        foreach (['presente_sin_copia', 'primera_vez', 'no_se'] as $motivo) {
            $this->responder('G5.1', $motivo);
            $this->assertNotContains('G5.2', $this->visibles(), "{$motivo} no pregunta qué años.");
            $this->assertSame([], $this->avisos(), "{$motivo} no le avisa nada al preparador.");
        }

        $this->responder('G5.1', 'no_presente_algunos');
        $this->assertContains('G5.2', $this->visibles());
        $this->assertFalse(app(MotorEntrevista::class)->situacion($this->entrevista->fresh())->tieneDocumento('declaracion-anterior'));
    }

    public function test_los_anos_sin_declarar_le_avisan_al_preparador_y_el_aviso_se_va_si_el_cliente_cambia_la_respuesta(): void
    {
        $this->responder('G5', 'no');
        $this->responder('G5.1', 'no_presente_algunos');
        $this->assertSame([['tipo' => 'anios_sin_declarar', 'detalle' => null]], $this->avisos());

        $this->responder('G5.2', ' 2022 y 2023 ');
        $this->assertSame([['tipo' => 'anios_sin_declarar', 'detalle' => '2022 y 2023']], $this->avisos());

        $this->responder('G5', 'si');
        $this->assertSame([], $this->avisos(), 'Con G5 = sí, G5.1 deja de estar visible y el aviso se va.');
    }
}
