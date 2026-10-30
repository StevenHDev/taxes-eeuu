<?php

namespace Tests\Feature\CatalogoFiscal;

use App\Enums\CatalogoFiscal\ObjetivoActivacion;
use App\Enums\UserRole;
use App\Models\CatalogoFiscal\CatalogoActivacion;
use App\Models\CatalogoFiscal\CatalogoCasilla;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoLinea;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\ParametroFiscal;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscalImportador;
use App\Services\CatalogoFiscal\CatalogoFiscalVerificador;
use App\Support\ParametrosFiscales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * F1.3: carga, copia y verificación del catálogo fiscal, con un catálogo
 * mínimo de prueba (tests/Fixtures/catalogo-fiscal).
 */
class CargaTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = base_path('tests/Fixtures/catalogo-fiscal');
        $this->app->instance(CatalogoFiscalImportador::class, new CatalogoFiscalImportador($this->fixtures));
    }

    /**
     * @return array<string, int>
     */
    private function conteos(int $anio): array
    {
        return app(CatalogoFiscalVerificador::class)->conteos($anio);
    }

    public function test_cargar_dos_veces_deja_lo_mismo(): void
    {
        $this->artisan('catalogo-fiscal:cargar', ['anio' => 2025])->assertSuccessful();
        $primera = $this->conteos(2025);

        $this->artisan('catalogo-fiscal:cargar', ['anio' => 2025])->assertSuccessful();

        $this->assertSame($primera, $this->conteos(2025));
        $this->assertSame(
            ['preguntas' => 4, 'documentos' => 2, 'casillas' => 3, 'formularios' => 3, 'lineas' => 6, 'activaciones' => 3, 'reglas' => 1],
            $primera,
        );
        $this->assertSame('1961-01-02', ParametrosFiscales::valor(2025, 'edad', 'nacido_antes_de_mayor_65'));
    }

    public function test_la_segunda_carga_no_cambia_nada(): void
    {
        $importador = app(CatalogoFiscalImportador::class);
        $importador->cargar(2025);

        $segunda = $importador->cargar(2025);

        $this->assertSame([], $segunda->creados);
        $this->assertSame([], $segunda->actualizados);
        $this->assertSame(4, $segunda->sinCambios['pregunta']);
    }

    public function test_dry_run_no_escribe(): void
    {
        $this->artisan('catalogo-fiscal:cargar', ['anio' => 2025, '--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();

        $this->assertSame(0, CatalogoPregunta::query()->count());
        $this->assertNull(ParametrosFiscales::valor(2025, 'edad', 'nacido_antes_de_mayor_65'));
    }

    public function test_no_pisa_lo_editado_en_pantalla_salvo_con_forzar(): void
    {
        $importador = app(CatalogoFiscalImportador::class);
        $importador->cargar(2025);

        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        CatalogoPregunta::query()->where('codigo', 'D1')->sole()->update(['texto_es' => 'Editado por GTS']);
        auth()->logout();

        $resultado = $importador->cargar(2025);
        $this->assertSame('Editado por GTS', CatalogoPregunta::query()->where('codigo', 'D1')->value('texto_es'));
        $this->assertStringContainsString('pregunta D1: editado desde la pantalla', $resultado->saltados[0]);

        $importador->cargar(2025, forzar: true);
        $this->assertSame('¿Recibiste salario como empleado en {anio}?', CatalogoPregunta::query()->where('codigo', 'D1')->value('texto_es'));
    }

    public function test_una_clave_mal_escrita_detiene_la_carga(): void
    {
        $base = sys_get_temp_dir().'/catalogo-'.uniqid();
        mkdir("{$base}/2025", recursive: true);
        file_put_contents("{$base}/2025/reglas.php", "<?php return [['codigo' => 'R-01', 'descripcion' => 'x', 'severidad' => 'bloqueante', 'fuente' => 'typo']];");

        try {
            (new CatalogoFiscalImportador($base))->cargar(2025);
            $this->fail('Debió fallar por la clave "fuente".');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Claves desconocidas en reglas.php fila 1: fuente', $e->getMessage());
        } finally {
            array_map('unlink', glob("{$base}/2025/*"));
            rmdir("{$base}/2025");
            rmdir($base);
        }

        $this->assertSame(0, CatalogoPregunta::query()->count());
    }

    public function test_verificar_pasa_y_falla_cuando_algo_se_rompe(): void
    {
        app(CatalogoFiscalImportador::class)->cargar(2025);
        $this->artisan('catalogo-fiscal:verificar', ['anio' => 2025])->expectsOutputToContain('sin problemas')->assertSuccessful();

        CatalogoActivacion::query()->create([
            'tax_year' => 2025, 'pregunta_id' => CatalogoPregunta::query()->where('codigo', 'D1')->value('id'),
            'respuesta' => 'si', 'objetivo_tipo' => ObjetivoActivacion::Documento, 'objetivo_codigo' => 'no-existe',
        ]);
        CatalogoFormulario::query()->where('codigo', '1040')->update(['siempre' => false]);

        $this->artisan('catalogo-fiscal:verificar', ['anio' => 2025])
            ->expectsOutputToContain('D1 activa documento "no-existe"')
            ->expectsOutputToContain('El 1040 debe existir y ser el único')
            ->assertFailed();
    }

    public function test_verificar_detecta_un_origen_de_linea_roto(): void
    {
        app(CatalogoFiscalImportador::class)->cargar(2025);
        CatalogoLinea::query()->where('linea', '19')->update(['origen_clave' => 'sch-8812:99']);

        $this->assertContains('1040 línea 19: el origen "sch-8812:99" no existe en 2025.', app(CatalogoFiscalVerificador::class)->verificar(2025));
    }

    public function test_copiar_a_2026_deja_todo_sin_validar_y_los_montos_pendientes(): void
    {
        $importador = app(CatalogoFiscalImportador::class);
        $importador->cargar(2025);
        CatalogoPregunta::query()->where('codigo', 'D1')->update(['validado_en' => now()]);

        $this->artisan('catalogo-fiscal:copiar', ['desde' => 2025, 'hasta' => 2026])->assertSuccessful();
        $resultado = $importador->cargar(2026);

        $this->assertSame($this->conteos(2025), $this->conteos(2026));
        $this->assertNull(CatalogoPregunta::query()->delAnio(2026)->where('codigo', 'D1')->value('validado_en'));
        $this->assertSame('¿Recibiste salario como empleado en 2026?', CatalogoPregunta::query()->delAnio(2026)->where('codigo', 'D1')->sole()->texto());

        // Ajustes de 2026 del documento madre.
        $this->assertSame(5300, ParametrosFiscales::valor(2026, 'dependiente_calificado', 'limite_ingreso_bruto_pariente_calificado'));
        $this->assertSame('1962-01-02', ParametrosFiscales::valor(2026, 'edad', 'nacido_antes_de_mayor_65'));
        $this->assertTrue(CatalogoCasilla::query()->delAnio(2026)->where('casilla', '1a')->exists());
        $this->assertSame('1099-nec:1a', CatalogoLinea::query()->delAnio(2026)->where('linea', '1')->value('origen_clave'));
        $this->assertSame('1040 línea 1a (2026)', CatalogoPregunta::query()->delAnio(2026)->where('codigo', 'D1')->value('fuente_texto'));
        $this->assertContains('Ajuste: el parámetro no.existe no existe en 2026.', $resultado->avisos);

        // El resto de los montos queda pendiente y 2025 no cambia.
        $this->assertNull(ParametrosFiscales::valor(2026, 'deduccion_estandar', 'monto_mfj'));
        $this->assertSame(31500, ParametrosFiscales::valor(2025, 'deduccion_estandar', 'monto_mfj'));
        $this->assertSame('1', CatalogoCasilla::query()->delAnio(2025)->whereHas('documento', fn ($q) => $q->where('codigo', '1099-nec'))->value('casilla'));
        $this->assertSame(
            ParametroFiscal::query()->where('tax_year', 2025)->count(),
            ParametroFiscal::query()->where('tax_year', 2026)->count(),
        );

        $this->assertSame([], app(CatalogoFiscalVerificador::class)->verificar(2026));
    }

    public function test_un_anio_copiado_hereda_lo_nuevo_del_origen_y_recargarlo_no_cambia_nada(): void
    {
        $importador = app(CatalogoFiscalImportador::class);
        $importador->cargar(2025);
        $this->artisan('catalogo-fiscal:copiar', ['desde' => 2025, 'hasta' => 2026])->assertSuccessful();
        $importador->cargar(2026);

        // Una pieza que se sumó a los archivos de 2025 después de copiar.
        CatalogoPregunta::query()->delAnio(2026)->where('codigo', 'D1')->sole()->activaciones()->delete();
        CatalogoPregunta::query()->delAnio(2026)->where('codigo', 'D1')->delete();

        $importador->cargar(2026);
        $this->assertSame('1040 línea 1a (2026)', CatalogoPregunta::query()->delAnio(2026)->where('codigo', 'D1')->value('fuente_texto'));
        $this->assertSame($this->conteos(2025), $this->conteos(2026));

        $otra = $importador->cargar(2026);
        $this->assertSame([], $otra->creados);
        $this->assertSame([], $otra->actualizados);
        $this->assertSame(1, CatalogoCasilla::query()->delAnio(2026)->whereHas('documento', fn ($q) => $q->where('codigo', '1099-nec'))->where('casilla', '1a')->count());
    }

    public function test_no_copia_sobre_un_anio_con_contenido(): void
    {
        app(CatalogoFiscalImportador::class)->cargar(2025);
        $this->artisan('catalogo-fiscal:copiar', ['desde' => 2025, 'hasta' => 2026])->assertSuccessful();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('2026 ya tiene catálogo');
        $this->artisan('catalogo-fiscal:copiar', ['desde' => 2025, 'hasta' => 2026]);
    }

    public function test_la_carga_no_llena_la_bitacora_general(): void
    {
        app(CatalogoFiscalImportador::class)->cargar(2025);

        $this->assertDatabaseMissing('bitacora_actividad', ['auditable_type' => CatalogoPregunta::class]);
    }
}
