<?php

namespace Tests\Feature\CatalogoFiscal;

use App\Http\Requests\CatalogoFiscal\DocumentoRequest;
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
        $this->assertSame(100, $conteos['preguntas']);
        $this->assertSame(47, $conteos['documentos']);
        $this->assertSame(29, $conteos['formularios']);
        $this->assertSame(13, $conteos['reglas']);
        $this->assertSame(347, $conteos['casillas']);
        $this->assertSame(98, $conteos['lineas']);

        // 2026 apaga F10 y F11: los créditos por vehículo limpio y de energía
        // de la vivienda terminan en 2025 (F3.9).
        $this->assertSame([...$conteos, 'preguntas' => 98], $verificador->conteos(2026));
    }

    public function test_los_creditos_que_terminan_en_2025_solo_se_preguntan_en_2025(): void
    {
        $codigos = fn (int $anio) => $this->lector->preguntas($anio)->pluck('codigo');

        $this->assertTrue($codigos(2025)->contains('F10'));
        $this->assertTrue($codigos(2025)->contains('F11'));
        $this->assertFalse($codigos(2026)->contains('F10'));
        $this->assertFalse($codigos(2026)->contains('F11'));
        $this->assertTrue($codigos(2026)->contains('F12'), 'El crédito por adopción sigue en 2026.');
        $this->assertTrue($codigos(2026)->contains('F1.1'));

        $f11 = $this->lector->preguntas(2025)->firstWhere('codigo', 'F11');
        $this->assertEqualsCanonicalizing(['documento:facturas-energia', 'formulario:f5695'], collect($f11['activaciones'])->map(fn ($a) => "{$a['objetivo_tipo']}:{$a['objetivo_codigo']}")->all());
    }

    public function test_preguntas_por_bloque_como_la_seccion_3(): void
    {
        $porBloque = CatalogoPregunta::query()->delAnio(2025)->get()->countBy('bloque')->sortKeys()->all();

        $this->assertSame(['A' => 17, 'B' => 9, 'C' => 16, 'D' => 26, 'E' => 10, 'F' => 15, 'G' => 7], $porBloque);
        $this->assertSame(15, CatalogoPregunta::query()->delAnio(2025)->where('por_persona', true)->count());
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
        $this->assertContains('1a', collect($nec2026['casillas'])->pluck('casilla')->all());
        $this->assertNotContains('1', collect($nec2026['casillas'])->pluck('casilla')->all());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function casillasDe(int $anio, string $documento): Collection
    {
        return collect($this->lector->documentos($anio)->firstWhere('codigo', $documento)['casillas'] ?? []);
    }

    public function test_la_tarjeta_del_seguro_social_se_lee_con_su_nombre_y_su_numero_sensibles(): void
    {
        foreach ([2025, 2026] as $anio) {
            $casillas = $this->casillasDe($anio, 'tarjeta-ssn')->keyBy('casilla');

            $this->assertSame(['nombre', 'ssn', 'no-valido-para-trabajar'], $casillas->keys()->all(), "{$anio}: casillas de la tarjeta.");
            $this->assertTrue($casillas['ssn']['sensible']);
            $this->assertTrue($casillas['nombre']['sensible']);
            $this->assertSame('ssn', $casillas['ssn']['tipo_dato']);
            $this->assertFalse($casillas->has('anio'), 'La tarjeta no trae año impreso.');
        }
    }

    public function test_los_20_documentos_que_lee_f4_traen_sus_casillas_con_el_anio(): void
    {
        $documentos = ['w2', '1095-a', '1099-nec', '1099-k', '1099-misc', '1099-int', '1099-div', '1099-r', 'ssa-1099', '1099-g', '1098', '1098-e', '1098-t', '1099-s', '1099-c', '1099-sa', '5498-sa', 'w2g', 'estado-cuenta-ira', '1099-q'];

        foreach ([2025, 2026] as $anio) {
            foreach ($documentos as $codigo) {
                $casillas = $this->casillasDe($anio, $codigo)->pluck('casilla');
                $this->assertTrue($casillas->contains('anio'), "{$anio} {$codigo}: falta el año.");
                $this->assertGreaterThanOrEqual(6, $casillas->count(), "{$anio} {$codigo}: pocas casillas.");

                if ($codigo !== 'w2' && $codigo !== '1095-a') {
                    $this->assertTrue($casillas->contains('receptor-tin'), "{$anio} {$codigo}: falta el TIN del receptor.");
                }
            }
        }

        $w2 = $this->casillasDe(2025, 'w2')->pluck('casilla');
        foreach (['a', 'b', 'c', 'e', 'anio', '1', '2', '3', '4', '7', '10', '12a-codigo', '12a-monto', '12d-codigo', '12d-monto'] as $casilla) {
            $this->assertTrue($w2->contains($casilla), "W-2: falta {$casilla}.");
        }
        $this->assertFalse($w2->contains('12'));
    }

    public function test_la_1095_a_trae_una_casilla_por_linea_y_no_las_resumidas(): void
    {
        $casillas = $this->casillasDe(2025, '1095-a')->pluck('casilla');

        $esperadas = [];
        foreach (range(16, 20) as $fila) {
            foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
                $esperadas[] = "{$fila}-{$col}";
            }
        }
        foreach (range(21, 32) as $mes) {
            foreach (['A', 'B', 'C'] as $col) {
                $esperadas[] = "{$mes}-{$col}";
            }
        }
        array_push($esperadas, '33-A', '33-B', '33-C');

        foreach ($esperadas as $casilla) {
            $this->assertTrue($casillas->contains($casilla), "1095-A: falta {$casilla}.");
        }
        foreach (['parte-I', 'parte-II', 'III-A', 'III-B', 'III-C', 'linea-33'] as $vieja) {
            $this->assertFalse($casillas->contains($vieja), "1095-A: sobra {$vieja}.");
        }

        // Cada columna del 8962 sale de una sola casilla: ";" en el origen
        // suma, y la prima, la de referencia y el adelanto no se suman.
        $this->assertSame('1095-a:33-A', $this->origen('f8962', '11a'));
        $this->assertSame('1095-a:33-B', $this->origen('f8962', '11b'));
        $this->assertSame('1095-a:33-C', $this->origen('f8962', '11f'));
        $this->assertSame('1095-a:21-A', $this->origen('f8962', '12a'), 'Línea 12 = enero = línea 21 del 1095-A.');
        $this->assertSame('1095-a:32-C', $this->origen('f8962', '23f'), 'Línea 23 = diciembre = línea 32 del 1095-A.');
        $this->assertNull($this->origen('f8962', 'col-a'));
    }

    public function test_toda_linea_de_origen_casilla_apunta_a_una_casilla_activa_y_lo_sensible_esta_marcado(): void
    {
        $permitidos = DocumentoRequest::TIPOS_DATO;

        foreach ([2025, 2026] as $anio) {
            $activas = [];
            foreach ($this->lector->documentos($anio) as $documento) {
                foreach ($documento['casillas'] as $c) {
                    $activas["{$documento['codigo']}:{$c['casilla']}"] = true;
                    $this->assertContains($c['tipo_dato'], $permitidos, "{$anio} {$documento['codigo']}:{$c['casilla']}: tipo de dato.");

                    if (in_array($c['tipo_dato'], ['ssn', 'ein', 'tin'], true)) {
                        $this->assertTrue($c['sensible'], "{$anio} {$documento['codigo']}:{$c['casilla']} debe ser sensible.");
                    }
                }
            }

            foreach ($this->lector->formularios($anio) as $formulario) {
                foreach ($formulario['lineas'] as $linea) {
                    if ($linea['origen_tipo'] !== 'casilla') {
                        continue;
                    }
                    foreach (explode(';', $linea['origen_clave']) as $clave) {
                        $this->assertArrayHasKey($clave, $activas, "{$anio} {$formulario['codigo']} línea {$linea['linea']}: {$clave}.");
                    }
                }
            }
        }
    }

    public function test_los_documentos_con_fecha_dicen_quien_los_envia_que_hacer_y_de_donde_sale_la_fecha(): void
    {
        // F3.1: lo que ve el cliente en cada fila del checklist (5.2).
        $documentos = app(CatalogoFiscal::class)->documentos(2025);
        $conFecha = $documentos->filter(fn (array $d) => $d['fecha_llegada'] !== null);

        $this->assertGreaterThanOrEqual(20, $conFecha->count());

        foreach ($conFecha as $d) {
            $this->assertNotEmpty($d['emisor_es'], "{$d['codigo']}: falta quién lo envía.");
            $this->assertNotEmpty($d['emisor_en'], "{$d['codigo']}: falta quién lo envía (inglés).");
            $this->assertStringContainsString('a más tardar', (string) $d['fuente_texto'], "{$d['codigo']}: la fecha no dice su fuente.");
        }

        foreach ($documentos as $d) {
            $this->assertNotEmpty($d['si_no_llega_es'], "{$d['codigo']}: falta qué hacer si no llega.");
            $this->assertNotEmpty($d['si_no_llega_en'], "{$d['codigo']}: falta qué hacer si no llega (inglés).");
        }
    }

    public function test_solo_los_registros_propios_traen_que_subir_y_plantilla(): void
    {
        foreach ([2025, 2026] as $anio) {
            $documentos = $this->lector->documentos($anio);
            $conTexto = $documentos->filter(fn (array $d) => ! empty($d['que_subir_es']) || ! empty($d['que_subir_en']) || ! empty($d['plantilla']));

            $this->assertEqualsCanonicalizing(['registros-ingresos-gastos', 'registros-alquiler'], $conTexto->pluck('codigo')->all());

            foreach ($conTexto as $d) {
                $this->assertNotEmpty($d['que_subir_es'], "{$d['codigo']}: falta qué subir.");
                $this->assertNotEmpty($d['que_subir_en'], "{$d['codigo']}: falta qué subir (inglés).");
                $this->assertFileExists(public_path("plantillas/{$d['plantilla']}-es.xlsx"));
                $this->assertFileExists(public_path("plantillas/{$d['plantilla']}-en.pdf"));
            }
        }
    }

    public function test_las_imagenes_de_ejemplo_son_de_formularios_del_irs_y_existen(): void
    {
        $sinImagen = ['tarjeta-ssn', 'tarjeta-ssn-conyuge', 'ssa-1099', 'id-foto', 'registros-ingresos-gastos', 'registros-alquiler'];

        foreach ([2025, 2026] as $anio) {
            $documentos = $this->lector->documentos($anio);
            $conImagen = $documentos->filter(fn (array $d) => ! empty($d['imagen_ejemplo']));

            $this->assertNotEmpty($conImagen);

            foreach ($conImagen as $d) {
                $this->assertFileExists(public_path($d['imagen_ejemplo']), "{$d['codigo']}: falta la imagen de ejemplo.");
                $this->assertNotEmpty($d['nombre_oficial'], "{$d['codigo']}: la imagen es solo de formularios del IRS.");
            }

            foreach ($sinImagen as $codigo) {
                $this->assertEmpty($documentos->firstWhere('codigo', $codigo)['imagen_ejemplo'] ?? null, "{$codigo}: no es un formulario del IRS.");
            }
        }
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
