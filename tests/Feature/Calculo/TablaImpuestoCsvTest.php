<?php

namespace Tests\Feature\Calculo;

use App\Services\Calculo\Formularios\Impuesto\TablaImpuesto;
use App\Services\Calculo\Nucleo\Dinero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * 6.4e: la Tax Table completa (Pub. 1040 de 2025, p. 2 a 13) contra la calculadora, fila por fila.
 *
 * tests/Fixtures/irs/2025/tax-table.csv sale de `pdftotext -layout` del PDF fijado, no del código:
 * 2.062 filas (desde, hasta, soltero, mfj, mfs, hoh); el "hasta" es exclusivo. Se prueba en el
 * "desde", en el punto medio y en el último centavo antes del "hasta" de cada franja.
 */
class TablaImpuestoCsvTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS = ['soltero' => 'single', 'mfj' => 'mfj', 'mfs' => 'mfs', 'hoh' => 'hoh'];

    public function test_la_tax_table_completa_coincide_fila_por_fila(): void
    {
        $filas = CasosIrs::filasCsv(2025, 'tax-table.csv');

        $this->assertCount(2062, $filas);

        $comparadas = 0;

        foreach ($filas as $fila) {
            $desde = Dinero::deDolares((int) $fila['desde']);
            $hasta = Dinero::deDolares((int) $fila['hasta']);
            $medio = Dinero::deCentavos(intdiv($desde->centavos() + $hasta->centavos(), 2));
            $ultimo = Dinero::deCentavos($hasta->centavos() - 1);

            foreach (self::COLUMNAS as $columna => $estadoCivil) {
                $esperado = Dinero::deDolares((int) $fila[$columna])->centavos();

                foreach ([$desde, $medio, $ultimo] as $ingreso) {
                    $this->assertSame(
                        $esperado,
                        TablaImpuesto::paraFila($estadoCivil, $ingreso)->centavos(),
                        "{$columna}, fila {$fila['desde']}-{$fila['hasta']}, ingreso {$ingreso->texto()}",
                    );
                    $comparadas++;
                }
            }

            // QSS usa la columna de MFJ.
            $this->assertSame(Dinero::deDolares((int) $fila['mfj'])->centavos(), TablaImpuesto::paraFila('qss', $medio)->centavos());
        }

        $this->assertSame(2062 * 4 * 3, $comparadas);
    }

    public function test_las_filas_son_contiguas_y_terminan_en_cien_mil(): void
    {
        $filas = CasosIrs::filasCsv(2025, 'tax-table.csv');
        $anterior = 0;

        foreach ($filas as $fila) {
            $this->assertSame($anterior, (int) $fila['desde']);
            $anterior = (int) $fila['hasta'];
        }

        $this->assertSame(TablaImpuesto::LIMITE_DOLARES, $anterior);
    }
}
