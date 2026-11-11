<?php

namespace Tests\Feature\Calculo;

use App\Services\Calculo\Formularios\Form2441\Credito2441;
use App\Services\Calculo\Nucleo\Dinero;
use App\Support\ParametrosFiscales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * La tabla de la línea 8 del Form 2441 de 2025 (p. 1; tests/Fixtures/irs/2025/f2441-tabla.csv, 16 filas) contra
 * Credito2441. El valor esperado sale del CSV extraído del PDF, nunca del código.
 *
 * El formulario dice "If line 7 is: Over — But not over": cada fila empieza un centavo por encima de "desde" y
 * termina en "hasta" incluido; la última ("No limit") no tiene fin. Ojo: el comentario de TablasIrsTest decía
 * "cerradas en desde y abiertas en hasta"; el PDF dice lo contrario y aquí se sigue el PDF.
 */
class TablaF2441CsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_csv_trae_las_16_filas_y_el_parametro_tambien(): void
    {
        $this->assertCount(16, CasosIrs::filasCsv(2025, 'f2441-tabla.csv'));
        $this->assertCount(16, ParametrosFiscales::tabla(2025, 'credito_cuidado_dependientes', 'tabla')->filas());
    }

    public function test_cada_fila_coincide_en_su_primer_centavo_su_medio_y_su_ultimo_dolar(): void
    {
        $errores = [];
        $comparadas = 0;

        foreach (CasosIrs::filasCsv(2025, 'f2441-tabla.csv') as $fila) {
            $desde = Dinero::deDolares((int) $fila['desde']);
            $puntos = [$desde->mas(Dinero::deCentavos(1))];

            if ($fila['hasta'] === '' || $fila['hasta'] === null) {
                $puntos[] = $desde->mas(Dinero::deDolares(1000000));
            } else {
                $hasta = Dinero::deDolares((int) $fila['hasta']);
                $puntos[] = Dinero::deCentavos(intdiv($desde->centavos() + $hasta->centavos(), 2));
                $puntos[] = $hasta;
            }

            foreach ($puntos as $punto) {
                $comparadas++;
                $obtenido = Credito2441::decimalParaLinea7($punto);

                if ($obtenido !== $fila['decimal']) {
                    $errores[] = "línea 7 = {$punto->texto()}: tabla {$fila['decimal']}, motor {$obtenido}";
                }
            }
        }

        $this->assertSame([], $errores);
        $this->assertSame(15 * 3 + 2, $comparadas);
    }

    public function test_un_ingreso_de_cero_o_negativo_toma_la_primera_fila(): void
    {
        // La primera fila es "$0—15,000"; una línea 11a de cero o negativa queda en ella.
        $this->assertSame('0.35', Credito2441::decimalParaLinea7(Dinero::deCentavos(0)));
        $this->assertSame('0.35', Credito2441::decimalParaLinea7(Dinero::deDolares(-500)));
    }

    public function test_en_2025_la_conjunta_usa_la_misma_tabla(): void
    {
        // Form 2441 de 2025, línea 8: una sola columna para todos los estados civiles.
        $this->assertSame('0.20', Credito2441::decimalParaLinea7(Dinero::deDolares(100000), 2025, true));
    }
}
