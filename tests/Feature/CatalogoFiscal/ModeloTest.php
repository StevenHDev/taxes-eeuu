<?php

namespace Tests\Feature\CatalogoFiscal;

use App\Enums\CatalogoFiscal\FichaFormulario;
use App\Enums\CatalogoFiscal\NivelPregunta;
use App\Enums\CatalogoFiscal\ObjetivoActivacion;
use App\Enums\CatalogoFiscal\OrigenCambio;
use App\Enums\CatalogoFiscal\OrigenLinea;
use App\Enums\CatalogoFiscal\TipoRespuesta;
use App\Enums\UserRole;
use App\Models\CatalogoFiscal\CatalogoActivacion;
use App\Models\CatalogoFiscal\CatalogoCambio;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoLinea;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\User;
use App\Rules\CondicionCatalogoValida;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * F1.1: modelo de datos del catálogo fiscal por año.
 */
class ModeloTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pregunta(string $codigo, int $anio = 2025, array $extra = []): CatalogoPregunta
    {
        return CatalogoPregunta::query()->create([
            'tax_year' => $anio,
            'codigo' => $codigo,
            'bloque' => substr($codigo, 0, 1),
            'texto_es' => "¿Pregunta {$codigo} de {anio}?",
            'tipo_respuesta' => TipoRespuesta::SiNo,
            'nivel' => NivelPregunta::Entrada,
            ...$extra,
        ]);
    }

    public function test_un_codigo_es_unico_por_anio_y_se_repite_en_otro_anio(): void
    {
        $this->pregunta('A1', 2025);
        $this->pregunta('A1', 2026);

        $this->assertSame(2, CatalogoPregunta::query()->where('codigo', 'A1')->count());

        $this->expectException(QueryException::class);
        $this->pregunta('A1', 2025);
    }

    public function test_el_anio_del_texto_se_reemplaza_al_mostrarlo(): void
    {
        $this->assertSame('¿Pregunta A1 de 2026?', $this->pregunta('A1', 2026)->texto());
    }

    public function test_editar_deja_el_valor_anterior_el_nuevo_y_el_actor(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $pregunta = $this->pregunta('B1');

        $this->actingAs($admin);
        $pregunta->update(['texto_es' => 'Texto nuevo', 'nivel' => NivelPregunta::Seguimiento]);

        $cambios = CatalogoCambio::query()->where('auditable_id', $pregunta->id)->get()->keyBy('campo');

        $this->assertSame(['nivel', 'texto_es'], $cambios->keys()->sort()->values()->all());
        $this->assertSame('¿Pregunta B1 de {anio}?', $cambios['texto_es']->valor_anterior);
        $this->assertSame('Texto nuevo', $cambios['texto_es']->valor_nuevo);
        $this->assertSame('entrada', $cambios['nivel']->valor_anterior);
        $this->assertSame($admin->id, $cambios['texto_es']->actor_id);
        $this->assertSame(OrigenCambio::Pantalla, $cambios['texto_es']->origen);
        $this->assertSame(2025, $cambios['texto_es']->tax_year);
    }

    public function test_un_cambio_sin_usuario_queda_como_carga(): void
    {
        $pregunta = $this->pregunta('B1');

        $pregunta->update(['orden' => 5]);

        $cambio = CatalogoCambio::query()->sole();
        $this->assertSame(OrigenCambio::Carga, $cambio->origen);
        $this->assertNull($cambio->actor_id);
    }

    public function test_la_condicion_solo_acepta_preguntas_del_mismo_anio(): void
    {
        $this->pregunta('B1', 2025);
        $this->pregunta('C1', 2026);

        $valida = fn (mixed $condicion) => Validator::make(
            ['condicion' => $condicion],
            ['condicion' => [new CondicionCatalogoValida(2025)]],
        )->passes();

        $this->assertTrue($valida(['pregunta' => 'B1', 'es' => 'casado']));
        $this->assertTrue($valida(['alguna' => [['pregunta' => 'B1', 'es' => 'si'], ['todas' => [['pregunta' => 'B1', 'es' => 'no']]]]]));
        $this->assertTrue($valida(null));

        $this->assertFalse($valida(['pregunta' => 'C1', 'es' => 'si']), 'C1 existe solo en 2026');
        $this->assertFalse($valida(['pregunta' => 'B1']), 'falta "es"');
        $this->assertFalse($valida(['todas' => []]), 'lista vacía');
        $this->assertFalse($valida(['otra' => 'cosa']));
    }

    public function test_el_lector_cachea_por_anio_y_se_invalida_al_guardar(): void
    {
        $lector = app(CatalogoFiscal::class);
        $pregunta = $this->pregunta('A1');

        $this->assertSame(['A1'], $lector->preguntas(2025)->pluck('codigo')->all());
        $this->assertTrue(Cache::has(CatalogoFiscal::claveCache(2025)));

        $this->pregunta('A2');
        $this->assertFalse(Cache::has(CatalogoFiscal::claveCache(2025)));
        $this->assertSame(['A1', 'A2'], $lector->preguntas(2025)->pluck('codigo')->sort()->values()->all());

        $pregunta->update(['texto_es' => 'Otro']);
        $this->assertSame('Otro', $lector->preguntas(2025)->firstWhere('codigo', 'A1')['texto_es']);
        $this->assertTrue($lector->preguntas(2026)->isEmpty());
    }

    public function test_desactivar_no_borra_y_deja_de_aparecer_en_el_lector(): void
    {
        $lector = app(CatalogoFiscal::class);
        $pregunta = $this->pregunta('A1');
        $this->pregunta('A2');

        $pregunta->update(['activo' => false]);

        $this->assertDatabaseHas('catalogo_preguntas', ['codigo' => 'A1', 'activo' => false]);
        $this->assertSame(['A2'], $lector->preguntas(2025)->pluck('codigo')->all());
    }

    public function test_el_lector_trae_activaciones_lineas_y_el_1040_primero(): void
    {
        $lector = app(CatalogoFiscal::class);
        $d1 = $this->pregunta('D1');

        CatalogoActivacion::query()->create([
            'tax_year' => 2025, 'pregunta_id' => $d1->id, 'respuesta' => 'si',
            'objetivo_tipo' => ObjetivoActivacion::Documento, 'objetivo_codigo' => 'w2',
        ]);

        CatalogoFormulario::query()->create(['tax_year' => 2025, 'codigo' => '8812', 'nombre' => 'Schedule 8812', 'orden' => 1]);
        $f1040 = CatalogoFormulario::query()->create([
            'tax_year' => 2025, 'codigo' => '1040', 'nombre' => 'Form 1040', 'siempre' => true,
            'ficha' => FichaFormulario::Completa, 'orden' => 9,
        ]);
        CatalogoLinea::query()->create([
            'tax_year' => 2025, 'formulario_id' => $f1040->id, 'linea' => '1a', 'descripcion' => 'Salarios',
            'origen_tipo' => OrigenLinea::Casilla, 'origen_clave' => 'w2:1',
        ]);

        $this->assertSame('w2', $lector->activadosPor(2025, 'D1')->sole()['objetivo_codigo']);
        $this->assertSame(['1040', '8812'], $lector->formularios(2025)->pluck('codigo')->all());
        $this->assertSame('w2:1', $lector->formularios(2025)->first()['lineas'][0]['origen_clave']);
        $this->assertTrue($lector->activadosPor(2025, 'NO-EXISTE')->isEmpty());
    }

    public function test_las_piezas_principales_van_a_la_bitacora_general(): void
    {
        $this->pregunta('A1');

        $this->assertDatabaseHas('bitacora_actividad', [
            'auditable_type' => CatalogoPregunta::class,
            'etiqueta' => 'A1 (2025)',
        ]);
    }
}
