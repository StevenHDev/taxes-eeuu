<?php

namespace Tests\Feature\CatalogoFiscal;

use App\Enums\CatalogoFiscal\OrigenCambio;
use App\Enums\UserRole;
use App\Models\CatalogoFiscal\CatalogoCambio;
use App\Models\CatalogoFiscal\CatalogoCasilla;
use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\ParametroFiscal;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscalEditor;
use App\Support\ParametrosFiscales;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1.5: edición del catálogo fiscal por el administrador.
 */
class EdicionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        $this->admin = User::factory()->create(['role' => UserRole::Administrator]);
    }

    private function pregunta(string $codigo, int $anio = 2025): CatalogoPregunta
    {
        return CatalogoPregunta::query()->delAnio($anio)->where('codigo', $codigo)->sole();
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPregunta(CatalogoPregunta $p, array $cambios = []): array
    {
        return [
            'texto_es' => $p->texto_es,
            'tipo_respuesta' => $p->tipo_respuesta->value,
            'opciones' => $p->opciones ?? [],
            'nivel' => $p->nivel->value,
            'por_persona' => $p->por_persona,
            'sensible' => $p->sensible,
            'condicion' => $p->condicion,
            'efecto_es' => $p->efecto_es,
            'fuente_texto' => $p->fuente_texto,
            'fuente_url' => $p->fuente_url,
            ...$cambios,
        ];
    }

    public function test_el_administrador_edita_una_pregunta_y_queda_en_el_historial(): void
    {
        $d1 = $this->pregunta('D1');

        $this->actingAs($this->admin)
            ->patch(route('catalogo-fiscal.preguntas.update', ['anio' => 2025, 'pregunta' => $d1]), $this->datosPregunta($d1, ['texto_es' => '¿Trabajaste como empleado en {anio}?']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('¿Trabajaste como empleado en {anio}?', $d1->fresh()->texto_es);

        $cambio = CatalogoCambio::query()->where('auditable_id', $d1->id)->where('campo', 'texto_es')->sole();
        $this->assertSame($this->admin->id, $cambio->actor_id);
        $this->assertSame(OrigenCambio::Pantalla, $cambio->origen);
        $this->assertStringContainsString('salario', (string) $cambio->valor_anterior);
    }

    public function test_el_preparador_no_puede_editar_ni_desactivar(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $d1 = $this->pregunta('D1');

        $this->actingAs($preparador)->patch(route('catalogo-fiscal.preguntas.update', ['anio' => 2025, 'pregunta' => $d1]), $this->datosPregunta($d1))->assertForbidden();
        $this->actingAs($preparador)->post(route('catalogo-fiscal.reglas.store', ['anio' => 2025]), ['codigo' => 'R-20', 'descripcion' => 'x', 'severidad' => 'revision'])->assertForbidden();
        $this->actingAs($preparador)->patch(route('catalogo-fiscal.activo', ['anio' => 2025, 'tipo' => 'preguntas', 'id' => $d1->id]), ['activo' => false])->assertForbidden();
        $this->assertTrue($d1->fresh()->activo);
    }

    public function test_el_preparador_si_puede_validar_y_queda_su_nombre(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $d1 = $this->pregunta('D1');

        $this->actingAs($preparador)
            ->post(route('catalogo-fiscal.validar', ['anio' => 2025, 'tipo' => 'preguntas', 'id' => $d1->id]))
            ->assertSessionHasNoErrors();

        $d1->refresh();
        $this->assertNotNull($d1->validado_en);
        $this->assertSame($preparador->id, $d1->validado_por);
        $this->assertDatabaseHas('catalogo_cambios', ['auditable_id' => $d1->id, 'campo' => 'validado_por', 'actor_id' => $preparador->id]);
    }

    public function test_el_cliente_no_puede_validar(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $d1 = $this->pregunta('D1');

        $this->actingAs($cliente)
            ->post(route('catalogo-fiscal.validar', ['anio' => 2025, 'tipo' => 'preguntas', 'id' => $d1->id]))
            ->assertForbidden();
    }

    public function test_validar_exige_la_fuente_y_guarda_quien_y_cuando(): void
    {
        $regla = CatalogoRegla::query()->delAnio(2025)->where('codigo', 'R-02')->sole();
        $regla->update(['fuente_url' => null]);

        $this->actingAs($this->admin)
            ->post(route('catalogo-fiscal.validar', ['anio' => 2025, 'tipo' => 'reglas', 'id' => $regla->id]))
            ->assertSessionHasErrors('fuente_url');
        $this->assertNull($regla->fresh()->validado_en);

        $d1 = $this->pregunta('D1');
        $this->actingAs($this->admin)
            ->post(route('catalogo-fiscal.validar', ['anio' => 2025, 'tipo' => 'preguntas', 'id' => $d1->id]))
            ->assertSessionHasNoErrors();

        $d1->refresh();
        $this->assertNotNull($d1->validado_en);
        $this->assertSame($this->admin->id, $d1->validado_por);
    }

    public function test_editar_algo_validado_lo_vuelve_a_sin_validar_pero_el_orden_no(): void
    {
        $d1 = $this->pregunta('D1');
        $d1->forceFill(['validado_por' => $this->admin->id, 'validado_en' => now()])->save();

        app(CatalogoFiscalEditor::class)->guardar($d1, ['orden' => 99]);
        $this->assertNotNull($d1->fresh()->validado_en);

        $this->actingAs($this->admin)
            ->patch(route('catalogo-fiscal.preguntas.update', ['anio' => 2025, 'pregunta' => $d1]), $this->datosPregunta($d1->fresh(), ['efecto_es' => 'Otro efecto']))
            ->assertSessionHasNoErrors();

        $this->assertNull($d1->fresh()->validado_en);
    }

    public function test_cambiar_un_parametro_validado_lo_reabre_y_guarda_el_valor_anterior(): void
    {
        $parametro = ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => 'credito_ctc', 'clave' => 'monto_por_dependiente'])->sole();
        $parametro->forceFill(['validado_por' => $this->admin->id, 'validado_en' => now()])->save();

        $this->actingAs($this->admin)
            ->patch(route('catalogo-fiscal.parametros.update', ['anio' => 2025, 'parametro' => $parametro]), [
                'valor' => '2300', 'nombre_es' => $parametro->nombre_es, 'formulario_linea' => $parametro->formulario_linea,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2300, ParametrosFiscales::valor(2025, 'credito_ctc', 'monto_por_dependiente'));
        $this->assertNull($parametro->fresh()->validado_en);

        $cambio = CatalogoCambio::query()->where('auditable_type', ParametroFiscal::class)->where('auditable_id', $parametro->id)->where('campo', 'valor')->sole();
        $this->assertSame(2200, $cambio->valor_anterior);
        $this->assertSame(2300, $cambio->valor_nuevo);
    }

    public function test_un_parametro_se_puede_dejar_pendiente_y_valida_su_tipo(): void
    {
        $porcentaje = ParametroFiscal::query()->where(['tax_year' => 2025, 'clave' => 'porcentaje_maximo'])->sole();

        $this->actingAs($this->admin)
            ->patch(route('catalogo-fiscal.parametros.update', ['anio' => 2025, 'parametro' => $porcentaje]), ['valor' => '35', 'nombre_es' => 'x'])
            ->assertSessionHasErrors('valor');

        $this->actingAs($this->admin)
            ->patch(route('catalogo-fiscal.parametros.update', ['anio' => 2025, 'parametro' => $porcentaje]), ['valor' => null, 'nombre_es' => 'x'])
            ->assertSessionHasNoErrors();

        $this->assertNull(ParametrosFiscales::valor(2025, 'credito_cuidado_dependientes', 'porcentaje_maximo'));
    }

    public function test_no_se_cambia_el_codigo_ni_se_crea_uno_repetido(): void
    {
        $d1 = $this->pregunta('D1');

        $this->actingAs($this->admin)
            ->patch(route('catalogo-fiscal.preguntas.update', ['anio' => 2025, 'pregunta' => $d1]), $this->datosPregunta($d1, ['codigo' => 'D99']))
            ->assertSessionHasErrors('codigo');

        $this->actingAs($this->admin)
            ->post(route('catalogo-fiscal.reglas.store', ['anio' => 2025]), ['codigo' => 'R-04', 'descripcion' => 'x', 'severidad' => 'revision'])
            ->assertSessionHasErrors('codigo');
    }

    public function test_crear_una_regla_y_una_pregunta(): void
    {
        $this->actingAs($this->admin)
            ->post(route('catalogo-fiscal.reglas.store', ['anio' => 2025]), [
                'codigo' => 'R-14', 'descripcion' => 'Regla nueva', 'severidad' => 'informativa', 'fuente_texto' => 'Proceso',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(14, CatalogoRegla::query()->delAnio(2025)->count());

        $this->actingAs($this->admin)
            ->post(route('catalogo-fiscal.preguntas.store', ['anio' => 2025]), [
                'codigo' => 'D18', 'bloque' => 'D', 'texto_es' => '¿Recibiste otra cosa?', 'tipo_respuesta' => 'opciones',
                'opciones' => [['valor' => 'a', 'texto_es' => 'A'], ['valor' => 'b', 'texto_es' => 'B']],
                'nivel' => 'seguimiento', 'condicion' => ['pregunta' => 'D17', 'es' => 'si'],
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(18, $this->pregunta('D18')->orden);
    }

    public function test_validaciones_de_las_preguntas(): void
    {
        $base = ['codigo' => 'D19', 'bloque' => 'D', 'texto_es' => 'x', 'nivel' => 'entrada'];

        $this->actingAs($this->admin)
            ->post(route('catalogo-fiscal.preguntas.store', ['anio' => 2025]), [...$base, 'tipo_respuesta' => 'opciones', 'opciones' => []])
            ->assertSessionHasErrors('opciones');

        $this->actingAs($this->admin)
            ->post(route('catalogo-fiscal.preguntas.store', ['anio' => 2025]), [...$base, 'tipo_respuesta' => 'si_no', 'condicion' => ['pregunta' => 'Z9', 'es' => 'si']])
            ->assertSessionHasErrors('condicion');
    }

    public function test_editar_las_casillas_de_un_documento_desactiva_las_que_se_quitan_y_reabre_la_validacion(): void
    {
        $w2 = CatalogoDocumento::query()->delAnio(2025)->where('codigo', 'w2')->sole();
        $w2->forceFill(['validado_por' => $this->admin->id, 'validado_en' => now()])->save();

        $this->actingAs($this->admin)
            ->patch(route('catalogo-fiscal.documentos.update', ['anio' => 2025, 'documento' => $w2]), [
                'grupo' => $w2->grupo, 'nombre_sencillo_es' => $w2->nombre_sencillo_es, 'uno_por' => $w2->uno_por,
                'casillas' => [
                    ['casilla' => '1', 'descripcion' => 'Salarios', 'clave' => true],
                    ['casilla' => '2', 'descripcion' => 'Retención', 'clave' => true],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['1', '2'], CatalogoCasilla::query()->where('documento_id', $w2->id)->where('activo', true)->orderBy('orden')->pluck('casilla')->all());
        $this->assertGreaterThan(2, CatalogoCasilla::query()->where('documento_id', $w2->id)->count(), 'las demás se desactivan, no se borran');
        $this->assertNull($w2->fresh()->validado_en);
    }

    public function test_desactivar_y_reactivar_sin_borrar_y_el_1040_no_se_desactiva(): void
    {
        $regla = CatalogoRegla::query()->delAnio(2025)->where('codigo', 'R-13')->sole();

        $this->actingAs($this->admin)->patch(route('catalogo-fiscal.activo', ['anio' => 2025, 'tipo' => 'reglas', 'id' => $regla->id]), ['activo' => false])->assertRedirect();
        $this->assertFalse($regla->fresh()->activo);

        $this->actingAs($this->admin)->patch(route('catalogo-fiscal.activo', ['anio' => 2025, 'tipo' => 'reglas', 'id' => $regla->id]), ['activo' => true])->assertRedirect();
        $this->assertTrue($regla->fresh()->activo);

        $f1040 = CatalogoFormulario::query()->delAnio(2025)->where('codigo', '1040')->sole();
        $this->actingAs($this->admin)->patch(route('catalogo-fiscal.activo', ['anio' => 2025, 'tipo' => 'formularios', 'id' => $f1040->id]), ['activo' => false])->assertStatus(422);
        $this->assertTrue($f1040->fresh()->activo);
    }

    public function test_una_pieza_de_otro_anio_da_404(): void
    {
        $d1de2026 = $this->pregunta('D1', 2026);

        $this->actingAs($this->admin)
            ->patch(route('catalogo-fiscal.preguntas.update', ['anio' => 2025, 'pregunta' => $d1de2026]), $this->datosPregunta($d1de2026))
            ->assertNotFound();
    }
}
