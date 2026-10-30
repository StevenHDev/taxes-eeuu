<?php

namespace Tests\Feature\CatalogoFiscal;

use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\CatalogoFiscal\CatalogoFiscalVerificador;
use App\Support\ParametrosFiscales;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * F1.3: el contenido real de database/data/catalogo-fiscal (transcrito del
 * documento madre) carga, está conectado y cubre el caso María (4.8).
 */
class CatalogoRealTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoFiscal $lector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        $this->lector = app(CatalogoFiscal::class);
    }

    /**
     * @return Collection<int, string>
     */
    private function activados(string $pregunta): Collection
    {
        return $this->lector->activadosPor(2025, $pregunta)->map(fn (array $a) => "{$a['objetivo_tipo']}:{$a['objetivo_codigo']}");
    }

    private function origen(string $formulario, string $linea): ?string
    {
        $f = $this->lector->formularios(2025)->firstWhere('codigo', $formulario);

        return collect($f['lineas'] ?? [])->firstWhere('linea', $linea)['origen_clave'] ?? null;
    }

    public function test_2025_y_2026_cargan_sin_problemas(): void
    {
        $verificador = app(CatalogoFiscalVerificador::class);

        $this->assertSame([], $verificador->verificar(2025));
        $this->assertSame([], $verificador->verificar(2026));

        $conteos = $verificador->conteos(2025);
        $this->assertSame(79, $conteos['preguntas']);
        $this->assertSame(26, $conteos['formularios']);
        $this->assertSame(13, $conteos['reglas']);
        $this->assertSame($conteos, $verificador->conteos(2026));
    }

    public function test_preguntas_por_bloque_como_la_seccion_3(): void
    {
        $porBloque = CatalogoPregunta::query()->delAnio(2025)->get()->countBy('bloque')->sortKeys()->all();

        $this->assertSame(['A' => 15, 'B' => 6, 'C' => 15, 'D' => 19, 'E' => 9, 'F' => 10, 'G' => 5], $porBloque);
        $this->assertSame(14, CatalogoPregunta::query()->delAnio(2025)->where('por_persona', true)->count());
    }

    public function test_el_1040_es_el_unico_siempre_y_va_primero(): void
    {
        $this->assertSame(['1040'], CatalogoFormulario::query()->delAnio(2025)->where('siempre', true)->pluck('codigo')->all());
        $this->assertSame('1040', $this->lector->formularios(2025)->first()['codigo']);
    }

    public function test_caso_maria_esta_conectado_de_la_pregunta_al_1040(): void
    {
        // Salario, 1 empleo (D1) → W-2 → 1040 líneas 1a y 25a.
        $this->assertContains('documento:w2', $this->activados('D1'));
        $this->assertSame('w2:1', $this->origen('1040', '1a'));
        $this->assertSame('w2:2', $this->origen('1040', '25a'));

        // Hijo de 8 años con SSN (C2, C9) → Schedule 8812 → 1040 líneas 19 y 28, y Schedule EIC → 27a.
        $this->assertContains('formulario:sch-8812', $this->activados('C9'));
        $this->assertContains('formulario:sch-eic', $this->activados('C9'));
        $this->assertContains('documento:tarjeta-ssn', $this->activados('C9'));
        $this->assertSame('sch-8812:14', $this->origen('1040', '19'));
        $this->assertSame('sch-8812:27', $this->origen('1040', '28'));
        $this->assertStringStartsWith('sch-eic:', (string) $this->origen('1040', '27a'));

        // Pagó guardería (E6) → recibo del proveedor y Form 2441 → Schedule 3 línea 2 → 1040 línea 20.
        $this->assertContains('documento:recibo-cuidado', $this->activados('E6'));
        $this->assertContains('formulario:f2441', $this->activados('E6'));
        $this->assertSame('f2441:11', $this->origen('sch-3', '2'));
        $this->assertSame('sch-3:8', $this->origen('1040', '20'));

        // Estado civil y hogar (B1, B5) son preguntas de entrada.
        $this->assertSame('entrada', $this->lector->preguntas(2025)->firstWhere('codigo', 'B5')['nivel']);
    }

    public function test_2026_trae_los_ajustes_del_documento_madre_y_lo_demas_pendiente(): void
    {
        $this->assertSame(5300, ParametrosFiscales::valor(2026, 'dependiente_calificado', 'limite_ingreso_bruto_pariente_calificado'));
        $this->assertSame('1962-01-02', ParametrosFiscales::valor(2026, 'edad', 'nacido_antes_de_mayor_65'));
        $this->assertNull(ParametrosFiscales::valor(2026, 'deduccion_estandar', 'monto_mfj'));
        $this->assertSame(31500, ParametrosFiscales::valor(2025, 'deduccion_estandar', 'monto_mfj'));

        $c13 = fn (int $anio) => CatalogoPregunta::query()->delAnio($anio)->where('codigo', 'C13')->sole()->texto();
        $this->assertSame('¿Esta persona tuvo ingresos de menos de USD 5.200 en 2025?', $c13(2025));
        $this->assertSame('¿Esta persona tuvo ingresos de menos de USD 5.300 en 2026?', $c13(2026));

        $nec2026 = $this->lector->documentos(2026)->firstWhere('codigo', '1099-nec');
        $this->assertSame(['1a'], collect($nec2026['casillas'])->pluck('casilla')->all());
    }

    public function test_las_preguntas_sensibles_quedan_marcadas(): void
    {
        $sensibles = CatalogoPregunta::query()->delAnio(2025)->where('sensible', true)->pluck('codigo')->sort()->values()->all();

        // SSN o ITIN, IP PIN y cuentas bancarias: solo por el portal, nunca por WhatsApp.
        foreach (['A3', 'C9', 'G3', 'G4'] as $codigo) {
            $this->assertContains($codigo, $sensibles);
        }
    }
}
