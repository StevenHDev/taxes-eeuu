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
use Database\Seeders\CatalogoFiscalSeeder;
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

    /**
     * Parámetros de 2026 que siguen pendientes (sin valor) a propósito y por qué.
     * 6.1b: solo entra lo que está en un documento oficial del IRS y cabe en la forma del parámetro.
     *
     * @var array<string, string>
     */
    private const PENDIENTES_2026 = [
        'schedule_c.millaje_estandar' => 'dos tarifas en 2026 (72,5 centavos hasta el 30 jun. y 76 desde el 1 jul.) y el parámetro es un solo número',
        'credito_cuidado_dependientes.agi_umbral_porcentaje_minimo' => 'distinto para la conjunta (USD 206.000) y las demás (USD 103.000); el cálculo usa la tabla',
        'credito_ftc.umbral_de_minimis_soltero' => 'ni el borrador del Schedule 3 ni las instrucciones del 1040 de 2026 traen la cifra',
        'credito_ftc.umbral_de_minimis_mfj' => 'ni el borrador del Schedule 3 ni las instrucciones del 1040 de 2026 traen la cifra',
        'self_employment_tax.maximo_seguro_social_retenido_por_persona' => 'ningún documento del IRS de 2026 revisado trae la cifra',
    ];

    /**
     * Parámetros que no vienen del IRS y por eso no llevan fuente del IRS.
     *
     * @var list<string>
     */
    private const SIN_FUENTE_DEL_IRS = ['lectura.umbral_confianza'];

    public function test_los_parametros_de_2026_con_valor_traen_fuente_con_pagina_y_url_del_irs_y_el_resto_esta_pendiente_por_nombre(): void
    {
        $this->seed(CatalogoFiscalSeeder::class);

        $pendientes = [];

        foreach (ParametroFiscal::query()->where('tax_year', 2026)->get() as $p) {
            $codigo = "{$p->categoria}.{$p->clave}";

            if ($p->valor === null) {
                $pendientes[] = $codigo;

                continue;
            }

            if (in_array($codigo, self::SIN_FUENTE_DEL_IRS, true)) {
                continue;
            }

            $this->assertMatchesRegularExpression('/\bp\. ?\d+/', (string) $p->fuente_texto, "{$codigo} de 2026 tiene valor y no cita la página");
            $this->assertStringStartsWith('https://www.irs.gov/', (string) $p->fuente_url, "{$codigo} de 2026 tiene valor y no trae la URL del IRS");
            $this->assertFalse($p->estaValidado(), "{$codigo} de 2026 entra sin validar");
        }

        sort($pendientes);
        $esperados = array_keys(self::PENDIENTES_2026);
        sort($esperados);

        $this->assertSame($esperados, $pendientes);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('[schedule_c.millaje_estandar] de tax_year=2026 está pendiente');
        ParametrosFiscales::valorRequerido(2026, 'schedule_c', 'millaje_estandar');
    }

    public function test_cada_parametro_de_2025_tiene_su_valor_de_2026_o_figura_como_pendiente(): void
    {
        $ajustes = require database_path('data/catalogo-fiscal/2026/ajustes.php');
        $del2025 = [
            ...array_keys($this->metadatos2025()),
            ...array_map(fn (array $f) => "{$f['categoria']}.{$f['clave']}", require database_path('data/catalogo-fiscal/2025/parametros.php')),
        ];

        foreach ($ajustes['parametros'] as $codigo => $ajuste) {
            $this->assertContains($codigo, $del2025, "{$codigo} de 2026 no existe en 2025");
            $this->assertIsArray($ajuste, "{$codigo} de 2026 va con su fuente");
            $this->assertArrayHasKey('fuente_texto', $ajuste);
            $this->assertArrayHasKey('fuente_url', $ajuste);
        }

        $sinAjuste = array_values(array_diff($del2025, array_keys($ajustes['parametros'])));
        $sinAjuste = array_values(array_diff($sinAjuste, self::SIN_FUENTE_DEL_IRS));
        sort($sinAjuste);
        $pendientes = array_keys(self::PENDIENTES_2026);
        sort($pendientes);

        // Los pendientes que se dejan vacíos expresamente (valor nulo) también cuentan.
        $vacios = array_keys(array_filter($ajustes['parametros'], fn ($a) => $a['valor'] === null));
        $this->assertSame($pendientes, collect([...$sinAjuste, ...$vacios])->sort()->values()->all());
    }

    public function test_cargar_2026_no_toca_2025_y_las_cifras_de_2026_son_las_de_la_rev_proc(): void
    {
        $this->seed(CatalogoFiscalSeeder::class);

        // Cada una con su fuente en el archivo de ajustes: Rev. Proc. 2025-32 (p. 18, 10 a 12, 15) y Schedule SE de 2026.
        $this->assertSame(32200, ParametrosFiscales::valor(2026, 'deduccion_estandar', 'monto_mfj'));
        $this->assertSame(16100, ParametrosFiscales::valor(2026, 'deduccion_estandar', 'monto_mfs'));
        $this->assertSame(184500, ParametrosFiscales::valor(2026, 'self_employment_tax', 'tope_salario_social_security'));
        $this->assertSame(31500, ParametrosFiscales::valor(2025, 'deduccion_estandar', 'monto_mfj'));
        $this->assertSame(176100, ParametrosFiscales::valor(2025, 'self_employment_tax', 'tope_salario_social_security'));
        $this->assertSame('0.35', ParametrosFiscales::tabla(2025, 'credito_cuidado_dependientes', 'tabla')->buscar(15000)['decimal']);
        $this->assertSame(10918.2, ParametrosFiscales::valor(2025, 'self_employment_tax', 'maximo_seguro_social_retenido_por_persona'));
        $this->assertNull(ParametrosFiscales::valor(2026, 'self_employment_tax', 'maximo_seguro_social_retenido_por_persona'));
    }

    public function test_las_tablas_de_2026_se_leen_y_el_eic_es_coherente_con_sus_tasas(): void
    {
        $this->seed(CatalogoFiscalSeeder::class);

        // Tramos: cada uno empieza por encima del anterior y la última tasa es 37 %.
        foreach (['single', 'mfj', 'hoh', 'mfs'] as $estado) {
            $filas = ParametroFiscal::query()->where(['tax_year' => 2026, 'categoria' => 'tax_brackets', 'clave' => $estado])->sole()->valor;
            $this->assertCount(7, $filas);
            $this->assertSame(0.37, $filas[6]['tasa']);
            $this->assertSame($filas, collect($filas)->sortBy('desde')->values()->all());
        }

        // Crédito por cuidado: la tabla de 2026 trae las dos columnas y de 15.000 hacia abajo es 50 %.
        $cuidado = ParametrosFiscales::tabla(2026, 'credito_cuidado_dependientes', 'tabla');
        $this->assertSame('0.50', $cuidado->buscar(15000)['decimal']);
        $this->assertSame('0.20', $cuidado->buscar(Dinero::deDolares(103001))['decimal']);
        $fila = $cuidado->buscar(75000);
        $this->assertSame(['hasta' => 75000, 'hasta_conjunta' => 150000, 'decimal' => '0.35'], $fila);

        // EIC: máximo = ingreso ganado x tasa de entrada, redondeado a dólares (Rev. Proc. 2025-32, sección 4.06).
        foreach (ParametroFiscal::query()->where(['tax_year' => 2026, 'categoria' => 'credito_eic', 'clave' => 'por_hijos'])->sole()->valor as $f) {
            $calculado = Dinero::deDolares($f['ingreso_ganado'])->por($f['tasa_entrada'])->aDolares()->centavos();
            $this->assertSame($f['maximo'] * 100, $calculado, "EIC con {$f['hijos']} hijos: el máximo no sale de la tasa de entrada");
        }

        // Crédito de la prima: tabla 2 completa de 132 a 400 y sin tope de devolución.
        $cifra = ParametrosFiscales::tabla(2026, 'credito_prima_salud', 'tabla_cifra_aplicable');
        $this->assertSame('0.0210', $cifra->buscar(100)['decimal']);
        $this->assertSame('0.0314', $cifra->buscar(133)['decimal']);
        $this->assertSame('0.0996', $cifra->buscar(350)['decimal']);
    }
}
