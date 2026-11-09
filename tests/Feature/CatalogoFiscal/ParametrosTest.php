<?php

namespace Tests\Feature\CatalogoFiscal;

use App\Enums\CatalogoFiscal\GrupoParametro;
use App\Enums\CatalogoFiscal\OrigenCambio;
use App\Enums\CatalogoFiscal\TipoParametro;
use App\Enums\UserRole;
use App\Models\CatalogoFiscal\CatalogoCambio;
use App\Models\ParametroFiscal;
use App\Models\User;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Support\ParametrosFiscales;
use App\Support\TablaFiscal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1.2: parámetros del año sobre `parametros_fiscales`.
 */
class ParametrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_parametros_sembrados_tienen_grupo_tipo_y_nombre(): void
    {
        $sinGrupo = ParametroFiscal::query()->where('tax_year', 2025)->whereNull('grupo')->pluck('clave');

        $this->assertSame([], $sinGrupo->all());

        $estandar = ParametroFiscal::query()->where('categoria', 'deduccion_estandar')->where('clave', 'monto_mfj')->sole();
        $this->assertSame(GrupoParametro::DeduccionEstandar, $estandar->grupo);
        $this->assertSame(TipoParametro::Monto, $estandar->tipo);
        $this->assertSame('Form 1040, línea 12', $estandar->formulario_linea);
        $this->assertFalse($estandar->estaValidado());
        $this->assertSame(TipoParametro::Tabla, ParametroFiscal::query()->where('categoria', 'tax_brackets')->first()->tipo);
    }

    public function test_el_archivo_de_metadatos_cubre_todos_los_parametros_del_seeder(): void
    {
        $metadatos = require database_path('data/catalogo-fiscal/2025/parametros-metadatos.php');
        $sembrados = ParametroFiscal::query()->where('tax_year', 2025)->get()
            ->map(fn (ParametroFiscal $p) => "{$p->categoria}.{$p->clave}")->sort()->values()->all();

        $this->assertSame($sembrados, collect(array_keys($metadatos))->sort()->values()->all());

        foreach ($metadatos as $codigo => [$grupo, $tipo]) {
            $this->assertNotNull(GrupoParametro::tryFrom($grupo), "grupo inválido en {$codigo}");
            $this->assertNotNull(TipoParametro::tryFrom($tipo), "tipo inválido en {$codigo}");
        }
    }

    public function test_un_parametro_pendiente_no_inventa_una_cifra(): void
    {
        ParametroFiscal::query()->create([
            'tax_year' => 2026, 'categoria' => 'credito_ctc', 'clave' => 'monto_por_dependiente',
            'valor' => null, 'grupo' => GrupoParametro::Creditos, 'tipo' => TipoParametro::Monto,
        ]);

        $this->assertNull(ParametrosFiscales::valor(2026, 'credito_ctc', 'monto_por_dependiente'));

        $this->expectExceptionMessage('[credito_ctc.monto_por_dependiente] de tax_year=2026 está pendiente');
        ParametrosFiscales::valorRequerido(2026, 'credito_ctc', 'monto_por_dependiente');
    }

    public function test_un_parametro_que_no_existe_sigue_diciendo_que_falta_sembrarlo(): void
    {
        $this->expectExceptionMessage('Falta sembrar el parámetro fiscal [credito_ctc.monto_por_dependiente] para tax_year=2030');

        ParametrosFiscales::valorRequerido(2030, 'credito_ctc', 'monto_por_dependiente');
    }

    public function test_cambiar_2026_no_toca_2025_y_queda_en_el_historial(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $p2026 = ParametroFiscal::query()->create([
            'tax_year' => 2026, 'categoria' => 'credito_ctc', 'clave' => 'monto_por_dependiente', 'valor' => null,
        ]);
        $this->assertSame(2200, ParametrosFiscales::valor(2025, 'credito_ctc', 'monto_por_dependiente'));

        $this->actingAs($admin);
        $p2026->update(['valor' => 2300]);

        $this->assertSame(2300, ParametrosFiscales::valor(2026, 'credito_ctc', 'monto_por_dependiente'));
        $this->assertSame(2200, ParametrosFiscales::valor(2025, 'credito_ctc', 'monto_por_dependiente'));

        $cambio = CatalogoCambio::query()->where('auditable_type', ParametroFiscal::class)->sole();
        $this->assertNull($cambio->valor_anterior);
        $this->assertSame(2300, $cambio->valor_nuevo);
        $this->assertSame(OrigenCambio::Pantalla, $cambio->origen);
        $this->assertSame(2026, $cambio->tax_year);
    }

    public function test_el_lector_del_catalogo_trae_los_parametros_del_anio(): void
    {
        $lector = app(CatalogoFiscal::class);

        $this->assertSame(
            ParametroFiscal::query()->where('tax_year', 2025)->count(),
            $lector->parametros(2025)->count(),
        );
        $this->assertTrue($lector->parametros(2026)->isEmpty());
    }

    /**
     * Parámetros de 2025 que el motor necesita y que todavía no tienen cifra
     * verificada contra el IRS. Vacía a propósito: 6.1a verificó todos contra el
     * PDF del año. Si un parámetro vuelve a quedar pendiente (valor nulo), va
     * aquí con su motivo para que no pase inadvertido.
     *
     * @var array<string, string>
     */
    private const POR_VERIFICAR = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    private function metadatos2025(): array
    {
        return require database_path('data/catalogo-fiscal/2025/parametros-metadatos.php');
    }

    private function correrMigracionEspejo(): void
    {
        $migracion = require database_path('migrations/2026_11_09_160000_completa_parametros_fiscales_2025.php');
        $migracion->up();
    }

    public function test_cada_parametro_de_2025_del_motor_existe_no_esta_pendiente_y_trae_fuente_con_pagina(): void
    {
        foreach (array_keys($this->metadatos2025()) as $codigo) {
            [$categoria, $clave] = explode('.', $codigo, 2);
            $p = ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => $categoria, 'clave' => $clave])->first();

            $this->assertNotNull($p, "falta sembrar {$codigo}");

            if (array_key_exists($codigo, self::POR_VERIFICAR)) {
                $this->assertNull($p->valor, "{$codigo} está en POR_VERIFICAR pero ya tiene cifra: sácalo de la lista");

                continue;
            }

            $this->assertNotNull($p->valor, "{$codigo} está pendiente y no figura en POR_VERIFICAR");
            $this->assertMatchesRegularExpression('/\bp\. ?\d+/', (string) $p->fuente_texto, "{$codigo} no cita la página en su fuente");
            $this->assertStringStartsWith('https://www.irs.gov/', (string) $p->fuente_url, "{$codigo} no trae la URL del IRS");
        }
    }

    public function test_el_seeder_y_la_migracion_traen_los_mismos_parametros_y_valores(): void
    {
        $sembrados = ParametroFiscal::query()->where('tax_year', 2025)->pluck('valor', 'clave');
        $antes = ParametroFiscal::query()->where('tax_year', 2025)->count();

        $this->correrMigracionEspejo();

        $this->assertSame($antes, ParametroFiscal::query()->where('tax_year', 2025)->count());
        $this->assertSame(
            $sembrados->all(),
            ParametroFiscal::query()->where('tax_year', 2025)->pluck('valor', 'clave')->all(),
        );
    }

    public function test_las_cifras_de_las_constantes_del_codigo_pasaron_a_parametros(): void
    {
        $v = fn (string $c, string $k) => ParametrosFiscales::valorRequerido(2025, $c, $k);

        $this->assertSame('0.124', $v('self_employment_tax', 'tasa_seguro_social'));
        $this->assertSame('0.029', $v('self_employment_tax', 'tasa_medicare'));
        $this->assertSame('0.9235', $v('self_employment_tax', 'factor_ingreso_neto'));
        $this->assertSame(400, $v('self_employment_tax', 'minimo_ingreso_neto'));
        $this->assertSame('0.009', $v('additional_medicare_tax', 'tasa'));
        $this->assertSame(125000, $v('additional_medicare_tax', 'umbral_mfs'));
        $this->assertSame('0.20', $v('qbi', 'tasa'));
        $this->assertSame('0.038', $v('niit', 'tasa'));
        $this->assertSame(125000, $v('niit', 'umbral_mfs'));
        $this->assertSame(15750, $v('deduccion_estandar', 'monto_mfs'));
        $this->assertSame(1600, $v('deduccion_estandar', 'adicional_65_ciego_casado'));
        $this->assertSame(2000, $v('deduccion_estandar', 'adicional_65_ciego_soltero_hoh'));
        $this->assertSame(1350, $v('deduccion_estandar', 'dependiente_minimo'));
        $this->assertSame(450, $v('deduccion_estandar', 'dependiente_adicional_ingreso_trabajo'));
    }

    public function test_los_tramos_de_casado_por_separado_siguen_el_rev_proc_2024_40(): void
    {
        $mfs = ParametrosFiscales::valorRequerido(2025, 'tax_brackets', 'mfs');
        $soltero = ParametrosFiscales::valorRequerido(2025, 'tax_brackets', 'single');

        $this->assertCount(7, $mfs);
        $this->assertSame(['desde' => 375800, 'tasa' => 0.37], $mfs[6]);
        $this->assertSame(array_slice($soltero, 0, 6), array_slice($mfs, 0, 6));
    }

    public function test_la_tabla_del_2441_se_lee_como_tabla_y_respeta_los_bordes(): void
    {
        $tabla = ParametrosFiscales::tabla(2025, 'credito_cuidado_dependientes', 'tabla');

        $this->assertInstanceOf(TablaFiscal::class, $tabla);
        $this->assertCount(16, $tabla->filas());
        $this->assertSame('0.35', $tabla->buscar(15000)['decimal']);
        $this->assertSame('0.34', $tabla->buscar(15001)['decimal']);
        $this->assertSame('0.21', $tabla->buscar(43000)['decimal']);
        $this->assertSame('0.20', $tabla->buscar(43001)['decimal']);
        $this->assertSame('0.30', $tabla->buscar(Dinero::deDolares('24000.50'))['decimal']);
    }

    public function test_el_eic_trae_las_cifras_por_numero_de_hijos(): void
    {
        $tabla = ParametrosFiscales::tabla(2025, 'credito_eic', 'por_hijos');

        $this->assertSame(649, $tabla->buscar(0)['maximo']);
        $this->assertSame(4328, $tabla->buscar(1)['maximo']);
        $this->assertSame(7152, $tabla->buscar(2)['maximo']);
        $this->assertSame(8046, $tabla->buscar(3)['maximo']);
        $this->assertSame(8046, $tabla->buscar(5)['maximo'], 'tres o más hijos comparten la última fila');
        $this->assertSame(68675, $tabla->buscar(4)['fin_reduccion_mfj']);
        $this->assertSame(23350, $tabla->buscar(1)['inicio_reduccion_otros']);
        $this->assertSame(11950, ParametrosFiscales::valorRequerido(2025, 'credito_eic', 'tope_ingreso_inversion'));
    }

    public function test_el_8962_trae_la_cifra_aplicable_completa_y_el_tope_de_devolucion(): void
    {
        $cifra = ParametrosFiscales::tabla(2025, 'credito_prima_salud', 'tabla_cifra_aplicable');

        $this->assertCount(252, $cifra->filas(), 'menos de 150, 150 a 399 y 400 o más');
        $this->assertSame('0.0000', $cifra->buscar(100)['decimal']);
        $this->assertSame('0.0000', $cifra->buscar(150)['decimal']);
        $this->assertSame('0.0004', $cifra->buscar(151)['decimal']);
        $this->assertSame('0.0200', $cifra->buscar(200)['decimal']);
        $this->assertSame('0.0600', $cifra->buscar(300)['decimal']);
        $this->assertSame('0.0848', $cifra->buscar(399)['decimal']);
        $this->assertSame('0.0850', $cifra->buscar(400)['decimal']);

        $tope = ParametrosFiscales::tabla(2025, 'credito_prima_salud', 'tabla_tope_devolucion');
        $this->assertSame(375, $tope->buscar(199)['soltero']);
        $this->assertSame(1950, $tope->buscar(250)['otros']);
        $this->assertSame(1625, $tope->buscar(399)['soltero']);
        $this->assertNull($tope->buscar(400)['otros'], 'con 400 o más no hay tope');

        $pobreza = ParametrosFiscales::tabla(2025, 'credito_prima_salud', 'linea_pobreza_48_estados');
        $this->assertSame(15060, $pobreza->buscar(1)['monto']);
        $this->assertSame(52720, $pobreza->buscar(8)['monto']);
    }

    public function test_pedir_como_tabla_algo_que_no_es_tabla_o_esta_pendiente_truena_con_el_motivo(): void
    {
        try {
            ParametrosFiscales::tabla(2025, 'qbi', 'umbral_soltero');
            $this->fail('debía fallar: no es una tabla');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no es una tabla', $e->getMessage());
        }

        ParametroFiscal::query()->create(['tax_year' => 2026, 'categoria' => 'credito_eic', 'clave' => 'por_hijos', 'valor' => null]);

        $this->expectExceptionMessage('[credito_eic.por_hijos] de tax_year=2026 está pendiente');
        ParametrosFiscales::tabla(2026, 'credito_eic', 'por_hijos');
    }

    public function test_cambiar_la_tabla_de_2026_no_toca_la_de_2025(): void
    {
        $de2025 = ParametrosFiscales::tabla(2025, 'credito_cuidado_dependientes', 'tabla')->filas();

        ParametroFiscal::query()->create([
            'tax_year' => 2026, 'categoria' => 'credito_cuidado_dependientes', 'clave' => 'tabla',
            'valor' => [['hasta' => 1000, 'decimal' => '0.50'], ['hasta' => null, 'decimal' => '0.10']],
        ]);

        $this->assertSame('0.50', ParametrosFiscales::tabla(2026, 'credito_cuidado_dependientes', 'tabla')->buscar(900)['decimal']);
        $this->assertSame($de2025, ParametrosFiscales::tabla(2025, 'credito_cuidado_dependientes', 'tabla')->filas());
    }

    public function test_la_migracion_espejo_es_idempotente_y_no_pisa_valores_ni_validados(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $se = fn (string $c, string $k) => ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => $c, 'clave' => $k])->sole();

        // Uno existente sin fuente y con otra cifra: recibe la fuente, conserva su cifra.
        $qbi = $se('qbi', 'umbral_soltero');
        $qbi->forceFill(['valor' => 111, 'fuente_texto' => null, 'fuente_url' => null])->saveQuietly();

        // Uno validado: no se toca.
        $niit = $se('niit', 'umbral_mfj');
        $niit->forceFill(['fuente_texto' => null, 'fuente_url' => null, 'validado_por' => $admin->id, 'validado_en' => now()])->saveQuietly();

        // Uno nuevo que falta: se inserta.
        $se('self_employment_tax', 'tasa_medicare')->delete();
        // Uno nuevo que alguien ya corrigió: se respeta su cifra.
        $se('self_employment_tax', 'tasa_seguro_social')->forceFill(['valor' => '0.999'])->saveQuietly();

        $this->correrMigracionEspejo();
        $despues = ParametroFiscal::query()->where('tax_year', 2025)->count();
        $this->correrMigracionEspejo();

        $this->assertSame($despues, ParametroFiscal::query()->where('tax_year', 2025)->count());
        $this->assertSame(111, $se('qbi', 'umbral_soltero')->valor);
        $this->assertStringContainsString('p. 1', (string) $se('qbi', 'umbral_soltero')->fuente_texto);
        $this->assertNull($se('niit', 'umbral_mfj')->fuente_texto, 'un validado no se toca');
        $this->assertSame('0.029', $se('self_employment_tax', 'tasa_medicare')->valor);
        $this->assertSame('0.999', $se('self_employment_tax', 'tasa_seguro_social')->valor);
        $this->assertSame(15750, $se('deduccion_estandar', 'monto_mfs')->valor);
    }
}
