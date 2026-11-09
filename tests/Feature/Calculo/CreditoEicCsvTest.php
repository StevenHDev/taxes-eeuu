<?php

namespace Tests\Feature\Calculo;

use App\Services\Calculo\Formularios\Eitc\CreditoEic;
use App\Services\Calculo\Nucleo\Dinero;
use App\Support\ParametrosFiscales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * La EIC Table completa de 2025 (Pub. 1040 de 2025, pp. 16 a 27; tests/Fixtures/irs/2025/eic-table.csv, 1.374 filas
 * por 8 columnas) contra CreditoEic. El valor esperado sale del CSV extraído del PDF, nunca del código.
 * Las 8 celdas con asterisco se resuelven con eic-notas.csv (notas al pie de la tabla).
 */
class CreditoEicCsvTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS = ['solo_0', 'solo_1', 'solo_2', 'solo_3', 'mfj_0', 'mfj_1', 'mfj_2', 'mfj_3'];

    public function test_el_csv_trae_las_1374_filas_y_las_8_notas_con_asterisco(): void
    {
        $this->assertCount(1374, CasosIrs::filasCsv(2025, 'eic-table.csv'));
        $this->assertCount(8, CasosIrs::filasCsv(2025, 'eic-notas.csv'));
    }

    public function test_las_1374_filas_coinciden_en_el_borde_inferior_el_medio_y_el_ultimo_dolar_de_cada_franja(): void
    {
        $notas = $this->notas();
        $tabla = ParametrosFiscales::tabla(2025, 'credito_eic', 'por_hijos');
        $filas = CasosIrs::filasCsv(2025, 'eic-table.csv');
        $comparadas = 0;
        $errores = [];

        foreach ($filas as $fila) {
            $desde = (int) $fila['desde'];
            $hasta = (int) $fila['hasta'];

            foreach (self::COLUMNAS as $columna) {
                $celda = $fila[$columna];
                $esAsterisco = str_contains($celda, '*');

                if ($esAsterisco) {
                    // Nota: del inicio de la franja hasta antes del fin de la reducción el crédito es el de la nota;
                    // desde el fin "no puede tomar el crédito".
                    $nota = $notas["{$desde}|{$columna}"];
                    $puntos = [[$desde, $nota['credito']], [$nota['hasta_menos_de'] - 1, $nota['credito']], [$nota['hasta_menos_de'], 0], [$hasta - 1, 0]];
                } else {
                    $esperado = (int) $celda;
                    // El primer renglón es "1 a 50": el USD 0 no está en la tabla.
                    $puntos = [[max($desde, 1), $esperado], [$desde + 25, $esperado], [$hasta - 1, $esperado]];
                }

                foreach ($puntos as [$monto, $esperado]) {
                    $comparadas++;
                    $obtenido = CreditoEic::deLaTabla($tabla, $columna, Dinero::deDolares($monto))->centavos();

                    if ($obtenido !== $esperado * 100) {
                        $errores[] = "{$columna} monto {$monto}: tabla {$esperado}, motor ".($obtenido / 100);
                    }
                }
            }
        }

        $this->assertSame([], array_slice($errores, 0, 20), count($errores).' diferencias de '.$comparadas.' comparaciones');
        $this->assertSame(1374 * 8 * 3 - 8 * 3 + 8 * 4, $comparadas, 'cada celda con 3 puntos y las 8 con asterisco con 4');
    }

    public function test_para_fila_usa_los_parametros_del_ano(): void
    {
        // Pub. 1040 de 2025, p. 16: soltero, un hijo con SSN válido, monto de la hoja USD 2.455 -> USD 842.
        $this->assertSame(84200, CreditoEic::paraFila('solo_1', Dinero::deDolares(2455))->centavos());
    }

    public function test_cada_nota_con_asterisco_da_su_credito_y_desde_el_fin_de_la_reduccion_no_hay_credito(): void
    {
        $tabla = ParametrosFiscales::tabla(2025, 'credito_eic', 'por_hijos');

        // Pub. 1040 de 2025, notas de las pp. 19, 20, 24, 25, 26 y 27 (texto de las notas, no del código).
        $casos = [
            ['solo_0', 19100, 19104, 0],
            ['mfj_0', 26200, 26214, 1],
            ['solo_1', 50400, 50434, 3],
            ['solo_2', 57300, 57310, 1],
            ['mfj_1', 57550, 57554, 0],
            ['solo_3', 61550, 61555, 1],
            ['mfj_2', 64400, 64430, 3],
            ['mfj_3', 68650, 68675, 3],
        ];

        foreach ($casos as [$columna, $desde, $fin, $credito]) {
            $this->assertSame($credito * 100, CreditoEic::deLaTabla($tabla, $columna, Dinero::deDolares($desde))->centavos(), "{$columna} en {$desde}");
            $this->assertSame($credito * 100, CreditoEic::deLaTabla($tabla, $columna, Dinero::deDolares($fin - 1))->centavos(), "{$columna} en ".($fin - 1));
            $this->assertSame(0, CreditoEic::deLaTabla($tabla, $columna, Dinero::deDolares($fin))->centavos(), "{$columna} en {$fin}");
            $this->assertSame($fin * 100, CreditoEic::finReduccion($tabla, $columna)->centavos());
        }
    }

    /** @return array<string, array{hasta_menos_de: int, credito: int}> */
    private function notas(): array
    {
        $notas = [];

        foreach (CasosIrs::filasCsv(2025, 'eic-notas.csv') as $nota) {
            $notas["{$nota['desde']}|{$nota['columna']}"] = ['hasta_menos_de' => (int) $nota['credito_hasta_menos_de'], 'credito' => (int) $nota['credito']];
        }

        return $notas;
    }
}
