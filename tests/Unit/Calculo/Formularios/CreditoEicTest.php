<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Services\Calculo\Formularios\Eitc\CreditoEic;
use App\Services\Calculo\Nucleo\Dinero;
use App\Support\ParametrosFiscales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/** Bordes de CreditoEic. La tabla completa (1.374 filas) está en tests/Feature/Calculo/CreditoEicCsvTest.php. */
class CreditoEicTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_ejemplo_del_irs_y_los_centavos_dentro_de_la_misma_franja(): void
    {
        // Pub. 1040 de 2025, p. 16: soltero, un hijo, 2.450 a 2.500 -> 842. Los centavos no cambian la franja.
        $this->assertSame(84200, CreditoEic::paraFila('solo_1', Dinero::deDolares('2455'))->centavos());
        $this->assertSame(84200, CreditoEic::paraFila('solo_1', Dinero::deDolares('2450'))->centavos());
        $this->assertSame(84200, CreditoEic::paraFila('solo_1', Dinero::deDolares('2499.99'))->centavos());
        // 2.500 ya es la franja siguiente (p. 16: 2.500 a 2.550 = 859).
        $this->assertSame(85900, CreditoEic::paraFila('solo_1', Dinero::deDolares('2500'))->centavos());
    }

    public function test_cero_negativo_y_menos_de_un_dolar_no_tienen_credito(): void
    {
        // Pub. 1040, p. 16: la tabla empieza en "At least 1".
        foreach (['0', '-100', '0.99'] as $monto) {
            $this->assertSame(0, CreditoEic::paraFila('solo_1', Dinero::deDolares($monto))->centavos(), $monto);
        }
        // Primer renglón de la tabla: 1 a 50, 1 hijo = 9 (p. 16).
        $this->assertSame(900, CreditoEic::paraFila('solo_1', Dinero::deDolares('1'))->centavos());
    }

    public function test_el_fin_y_el_inicio_de_la_reduccion_son_los_de_las_instrucciones(): void
    {
        $tabla = ParametrosFiscales::tabla(2025, 'credito_eic', 'por_hijos');

        // Instrucciones del 1040 de 2025, p. 40, Paso 1, pregunta 1: 61.555 (68.675), 57.310 (64.430), 50.434 (57.554), 19.104 (26.214).
        $fines = ['solo_3' => 61555, 'mfj_3' => 68675, 'solo_2' => 57310, 'mfj_2' => 64430, 'solo_1' => 50434, 'mfj_1' => 57554, 'solo_0' => 19104, 'mfj_0' => 26214];
        foreach ($fines as $columna => $fin) {
            $this->assertSame($fin * 100, CreditoEic::finReduccion($tabla, $columna)->centavos(), $columna);
        }

        // Hoja A, línea 5 (p. 46): 10.620 (17.730) sin hijos; 23.350 (30.470) con uno o más.
        $inicios = ['solo_0' => 10620, 'mfj_0' => 17730, 'solo_1' => 23350, 'mfj_1' => 30470, 'solo_2' => 23350, 'mfj_3' => 30470];
        foreach ($inicios as $columna => $inicio) {
            $this->assertSame($inicio * 100, CreditoEic::inicioReduccion($tabla, $columna)->centavos(), $columna);
        }
    }

    public function test_una_columna_invalida_se_rechaza(): void
    {
        foreach (['solo_4', 'mfs_1', 'solo', 'mfj_-1', ''] as $columna) {
            try {
                CreditoEic::paraFila($columna, Dinero::deDolares('1000'));
                $this->fail("Aceptó la columna '{$columna}'.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_un_ano_sin_parametros_truena_para_que_la_calculadora_diga_cual_falta(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('credito_eic');

        CreditoEic::paraFila('solo_1', Dinero::deDolares('1000'), 2099);
    }

    public function test_el_credito_es_un_entero_de_dolares_y_nunca_negativo(): void
    {
        foreach (['solo_0', 'solo_1', 'solo_2', 'solo_3', 'mfj_0', 'mfj_1', 'mfj_2', 'mfj_3'] as $columna) {
            for ($monto = 0; $monto <= 75000; $monto += 251) {
                $centavos = CreditoEic::paraFila($columna, Dinero::deDolares($monto))->centavos();
                $this->assertGreaterThanOrEqual(0, $centavos);
                $this->assertSame(0, $centavos % 100);
            }
        }
    }
}
