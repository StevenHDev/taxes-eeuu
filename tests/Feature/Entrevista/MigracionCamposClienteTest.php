<?php

namespace Tests\Feature\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2.10: `catalogo-fiscal:equivalencias --migrar` pasa los datos del
 * formulario actual a la entrevista, con canal `migracion`, sin borrar
 * `campos_cliente` y sin mostrar valores.
 */
class MigracionCamposClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
    }

    private function cliente(): User
    {
        return User::factory()->create(['role' => UserRole::Client]);
    }

    private function campo(User $cliente, string $forma, string $campo, mixed $valor, ?Documento $documento = null, string $modo = 'texto'): void
    {
        CampoCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => $forma, 'campo' => $campo, 'tax_year' => 2025,
            'tipo_campo' => $documento ? 'documento' : 'dato', 'modo' => $documento ? 'archivo' : $modo,
            'valor_texto' => $valor, 'documento_id' => $documento?->id, 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);
    }

    private function documento(User $cliente, string $forma, string $campo): Documento
    {
        return Documento::query()->create([
            'user_id' => $cliente->id, 'forma' => $forma, 'tax_year' => 2025, 'campo' => $campo,
            'file_path' => "documentos/{$campo}.pdf", 'file_original_name' => "{$campo}.pdf", 'file_mime_type' => 'application/pdf',
            'file_size' => 100, 'formato' => 'pdf', 'estado_validacion' => 'recibido',
        ]);
    }

    /**
     * @return array<string, mixed> "codigo" o "codigo#orden de la persona" → valor
     */
    private function respuestas(User $cliente): array
    {
        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->firstOrFail();
        $ordenes = $entrevista->personas()->pluck('orden', 'id');

        return $entrevista->respuestas()->get()
            ->mapWithKeys(fn (EntrevistaRespuesta $r) => [
                $r->persona_id === null ? $r->pregunta_codigo : "{$r->pregunta_codigo}#{$ordenes[$r->persona_id]}" => $r->valor,
            ])->all();
    }

    private function empleadaConDependiente(): User
    {
        $cliente = $this->cliente();
        $this->campo($cliente, 'transversal', 'w2', null, $this->documento($cliente, 'transversal', 'w2'));
        $this->campo($cliente, 'transversal', 'estado_civil', [
            'casado_al_31_dic' => false, 'costeo_mas_mitad_hogar' => true, 'conyuge_fallecio_en_anio' => false, 'se_caso_en_anio' => false,
        ]);
        $this->campo($cliente, 'transversal', 'fecha_nacimiento_contribuyente', '1988-04-12');
        $this->campo($cliente, 'transversal', 'info_dependientes', [[
            'nombre_completo' => 'Sofía Pérez', 'fecha_nacimiento' => '2016-02-03', 'ssn' => '123-45-6789',
            'relacion' => 'Hija', 'meses_en_hogar' => 12, 'estudiante_tiempo_completo' => false, 'discapacitado' => false,
        ]]);
        $this->campo($cliente, 'form_1040', 'gastos_cuidado_dependientes', ['proveedor_nombre' => 'Guardería', 'monto_anual' => 3000]);
        $this->campo($cliente, 'form_1040', 'ingresos', ['salarios' => 30000]);
        $this->campo($cliente, 'form_1065', 'algo', 'fuera');

        return $cliente;
    }

    public function test_empleada_con_dependiente(): void
    {
        $cliente = $this->empleadaConDependiente();

        $this->artisan('catalogo-fiscal:equivalencias', ['--migrar' => true])
            ->doesntExpectOutputToContain('Sofía')
            ->doesntExpectOutputToContain('123-45-6789')
            ->doesntExpectOutputToContain('1988-04-12')
            ->assertSuccessful();

        $respuestas = $this->respuestas($cliente);
        $this->assertSame('si', $respuestas['D1']);
        $this->assertSame('si', $respuestas['B5']);
        $this->assertSame('1988-04-12', $respuestas['A1.1']);
        $this->assertSame('si', $respuestas['C0']);
        $this->assertSame('Sofía Pérez', $respuestas['C1#1']);
        $this->assertSame('2016-02-03', $respuestas['C2#1']);
        $this->assertSame('hijo', $respuestas['C3#1']);
        $this->assertSame(12, $respuestas['C4#1']);
        $this->assertSame('no', $respuestas['C7#1']);
        $this->assertArrayNotHasKey('B1', $respuestas, 'Casado = falso no dice cuál es el estado civil: se pregunta.');

        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->firstOrFail();
        $this->assertNotNull($entrevista->migrada_en);
        $this->assertSame(EstadoEntrevista::EnCurso, $entrevista->estado);
        $this->assertTrue($cliente->fresh()->entrevista_catalogo);
        $this->assertTrue($entrevista->respuestas()->get()->every(fn ($r) => $r->canal === CanalRespuesta::Migracion));

        $w2 = Documento::query()->where('user_id', $cliente->id)->where('campo', 'w2')->firstOrFail();
        $this->assertSame('w2', $w2->documento_codigo);
        $this->assertSame($entrevista->id, $w2->entrevista_id);
        $this->assertTrue(app(MotorEntrevista::class)->situacion($entrevista)->tieneDocumento('w2'));

        $this->assertSame(7, CampoCliente::query()->where('user_id', $cliente->id)->count(), 'campos_cliente queda como histórico.');
    }

    public function test_independiente_con_marketplace(): void
    {
        $cliente = $this->cliente();
        $this->campo($cliente, 'transversal', 'form_1099_nec', null, $this->documento($cliente, 'transversal', 'form_1099_nec'));
        $this->campo($cliente, 'schedule_c', 'ingresos_negocio', 25000);
        $this->campo($cliente, 'form_1040', 'marketplace_seguro', ['premium_mensual' => 420.5, 'slcsp' => 500, 'aptc_recibido' => 300]);
        $this->campo($cliente, 'transversal', 'form_1095_a', null, $this->documento($cliente, 'transversal', 'form_1095_a'));
        $this->campo($cliente, 'form_1040', 'deducciones', ['donaciones_efectivo' => 0, 'donaciones_bienes' => 200, 'intereses_hipotecarios' => 0]);
        $this->campo($cliente, 'transversal', 'form_1099_r', null, modo: 'no_aplica');

        $this->artisan('catalogo-fiscal:equivalencias', ['--migrar' => true])->assertSuccessful();

        $respuestas = $this->respuestas($cliente);
        $this->assertSame('si', $respuestas['D15']);
        $this->assertSame('si', $respuestas['F4']);
        $this->assertSame('si', $respuestas['E4'], 'Basta un monto mayor que 0 de los dos campos que van a E4.');
        $this->assertArrayNotHasKey('E1', $respuestas, 'Un monto en 0 no responde nada.');
        $this->assertArrayNotHasKey('D4', $respuestas, '"No aplica" no responde nada: se pregunta.');

        $situacion = app(MotorEntrevista::class)->situacion(Entrevista::query()->where('user_id', $cliente->id)->firstOrFail());
        $this->assertTrue($situacion->tieneFormulario('sch-c'));
        $this->assertSame(2, Documento::query()->where('user_id', $cliente->id)->whereNotNull('documento_codigo')->count());
    }

    public function test_dos_veces_no_duplica_y_no_pisa_lo_que_el_cliente_respondio(): void
    {
        $cliente = $this->empleadaConDependiente();
        $guardador = app(RespuestaGuardador::class);
        $entrevista = $guardador->entrevistaDe($cliente, CanalRespuesta::Portal);
        $guardador->guardar($entrevista, new RespuestaData('B5', 'no'), CanalRespuesta::Portal, $cliente);

        $this->artisan('catalogo-fiscal:equivalencias', ['--migrar' => true])->assertSuccessful();
        $antes = EntrevistaRespuesta::query()->count();

        $this->artisan('catalogo-fiscal:equivalencias', ['--migrar' => true])
            ->expectsOutputToContain('ya_migrado')
            ->assertSuccessful();

        $this->assertSame($antes, EntrevistaRespuesta::query()->count());
        $this->assertSame(1, $entrevista->personas()->count());
        $this->assertSame('no', $this->respuestas($cliente)['B5'], 'Lo que el cliente ya respondió en la entrevista no se pisa.');
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $cliente = $this->empleadaConDependiente();

        $this->artisan('catalogo-fiscal:equivalencias', ['--migrar' => true, '--dry-run' => true])
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();

        $this->assertSame(0, Entrevista::query()->count());
        $this->assertSame(0, EntrevistaRespuesta::query()->count());
        $this->assertFalse((bool) $cliente->fresh()->entrevista_catalogo);
        $this->assertSame(0, Documento::query()->whereNotNull('documento_codigo')->count());
    }

    public function test_solo_un_cliente(): void
    {
        $uno = $this->empleadaConDependiente();
        $otro = $this->empleadaConDependiente();

        $this->artisan('catalogo-fiscal:equivalencias', ['--migrar' => true, '--cliente' => $uno->id])->assertSuccessful();

        $this->assertTrue(Entrevista::query()->where('user_id', $uno->id)->exists());
        $this->assertFalse(Entrevista::query()->where('user_id', $otro->id)->exists());
    }

    public function test_el_cliente_migrado_ve_sus_respuestas_en_el_portal_con_canal_migracion(): void
    {
        $cliente = $this->empleadaConDependiente();
        $cliente->forceFill(['es_prueba' => true])->save();
        $this->artisan('catalogo-fiscal:equivalencias', ['--migrar' => true])->assertSuccessful();

        app(ConsentimientosCargador::class)->cargar();
        app(Consentimientos::class)->firmar($cliente, 2025, 1, 'Cliente', null, null);

        $this->actingAs($cliente->fresh())
            ->get(route('portal.entrevista.paso', ['pantalla' => 5, 'paso' => 'entrada']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('preguntas', fn ($preguntas) => collect($preguntas)
                ->contains(fn ($p) => $p['codigo'] === 'D1' && $p['valor'] === 'si' && $p['canal'] === 'migracion')));
    }
}
