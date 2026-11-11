<?php

namespace Tests\Feature\Calculo;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Form8962\TablasPrima;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * 6.4h: las tres tablas de las instrucciones del Form 8962 de 2025 (i8962) contra `TablasPrima`, completas.
 *
 * Los CSV de tests/Fixtures/irs/2025/ salen de `pdftotext -layout` del PDF fijado, no del código:
 * - f8962-pobreza.csv: Tablas 1-1, 1-2 y 1-3 (p. 8 y 9), 8 tamaños cada una más el adicional por persona.
 * - f8962-cifra-aplicable.csv: Tabla 2 (p. 11), una fila por porcentaje de 150 a 399, "menos de 150" y "400 o más".
 * - f8962-limite-devolucion.csv: Tabla 5 (p. 18); "hasta" es exclusivo ("less than 200") y la última fila
 *   ("400 or more") va vacía: la línea 28 queda en blanco.
 */
class TablasF8962CsvTest extends TestCase
{
    use RefreshDatabase;

    private const TABLAS = ['1-1' => TablasPrima::TABLA_48_ESTADOS, '1-2' => TablasPrima::TABLA_ALASKA, '1-3' => TablasPrima::TABLA_HAWAII];

    public function test_las_lineas_de_pobreza_coinciden_con_las_tablas_1_1_a_1_3_y_su_adicional(): void
    {
        $filas = CasosIrs::filasCsv(2025, 'f8962-pobreza.csv');
        $this->assertCount(24, $filas);

        foreach ($filas as $fila) {
            $tabla = self::TABLAS[$fila['tabla']];
            $tamano = (int) $fila['tamano'];

            $this->assertSame((int) $fila['monto'] * 100, TablasPrima::lineaPobreza(2025, $tabla, $tamano)->centavos(), "Tabla {$fila['tabla']}, tamaño {$tamano}");

            // La nota de cada tabla: más de 8 personas suma el adicional por cada persona de más (9, 10 y 11).
            if ($tamano === 8) {
                foreach ([1, 2, 3] as $demas) {
                    $esperado = ((int) $fila['monto'] + $demas * (int) $fila['adicional']) * 100;
                    $this->assertSame($esperado, TablasPrima::lineaPobreza(2025, $tabla, 8 + $demas)->centavos(), "Tabla {$fila['tabla']}, tamaño ".(8 + $demas));
                }
            }
        }

        // El ejemplo de la nota de la Tabla 1-1 (p. 8): familia de 11 → USD 68.860.
        $this->assertSame(6886000, TablasPrima::lineaPobreza(2025, TablasPrima::TABLA_48_ESTADOS, 11)->centavos());
    }

    public function test_la_cifra_aplicable_coincide_con_toda_la_tabla_2(): void
    {
        $filas = CasosIrs::filasCsv(2025, 'f8962-cifra-aplicable.csv');
        $this->assertCount(252, $filas);
        $comparadas = 0;

        foreach ($filas as $fila) {
            $desde = (int) $fila['desde'];
            $hasta = $fila['hasta'] === '' ? 401 : (int) $fila['hasta'];

            foreach (range($desde, $hasta) as $porcentaje) {
                $this->assertSame($fila['cifra'], TablasPrima::cifraAplicable(2025, $porcentaje), "línea 5 = {$porcentaje}");
                $comparadas++;
            }
        }

        $this->assertSame(402, $comparadas);
    }

    public function test_el_tope_de_la_devolucion_coincide_con_toda_la_tabla_5(): void
    {
        $filas = CasosIrs::filasCsv(2025, 'f8962-limite-devolucion.csv');
        $this->assertCount(4, $filas);
        $otros = [EstadoCivilIrs::Mfj, EstadoCivilIrs::Mfs, EstadoCivilIrs::Hoh, EstadoCivilIrs::Qss];

        foreach ($filas as $fila) {
            $desde = (int) $fila['desde'];
            $hasta = $fila['hasta'] === '' ? 450 : (int) $fila['hasta'] - 1;

            foreach (range($desde, $hasta) as $porcentaje) {
                $soltero = TablasPrima::topeDevolucion(2025, $porcentaje, EstadoCivilIrs::Single);
                $this->assertSame($fila['soltero'] === '' ? null : (int) $fila['soltero'] * 100, $soltero?->centavos(), "Single, línea 5 = {$porcentaje}");

                foreach ($otros as $estado) {
                    $tope = TablasPrima::topeDevolucion(2025, $porcentaje, $estado);
                    $this->assertSame($fila['otro'] === '' ? null : (int) $fila['otro'] * 100, $tope?->centavos(), "{$estado->value}, línea 5 = {$porcentaje}");
                }
            }
        }
    }
}
