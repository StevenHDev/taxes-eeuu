<?php

namespace Tests\Feature\Calculo;

use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * 6.2: estructura y citas de los casos del IRS, hash de los PDF fijados y sanidad de los CSV
 * extraídos. No toca la calculadora (ni existe aún).
 */
class CasosIrsTest extends TestCase
{
    private const ANIO = 2025;

    public function test_hay_casos_y_cada_uno_es_un_arreglo_con_su_codigo_igual_al_archivo(): void
    {
        $casos = CasosIrs::casos(self::ANIO);

        $this->assertNotEmpty($casos);

        foreach ($casos as $codigo => $caso) {
            $this->assertFileExists(CasosIrs::raiz().'/casos/'.self::ANIO."/{$codigo}.php");
            $this->assertSame(self::ANIO, $caso['anio'], $codigo);
            $this->assertNotEmpty($caso['descripcion'] ?? null, "{$codigo}: falta la descripción");
            $this->assertIsArray($caso['entradas'] ?? null, "{$codigo}: faltan las entradas");
            $this->assertArrayHasKey('contraste', $caso, $codigo);
        }
    }

    public function test_cada_caso_cita_linea_pagina_y_fuente(): void
    {
        $urls = collect(CasosIrs::fuentes(self::ANIO)['documentos'])->pluck('url', 'doc');

        foreach (CasosIrs::casos(self::ANIO) as $codigo => $caso) {
            $this->assertNotEmpty($caso['pasos'] ?? null, "{$codigo}: sin pasos");

            foreach ($caso['pasos'] as $i => $paso) {
                $donde = "{$codigo}, paso {$i}";

                foreach (['formulario', 'linea', 'texto', 'valor'] as $clave) {
                    $this->assertNotSame('', trim((string) ($paso[$clave] ?? '')), "{$donde}: falta {$clave}");
                }

                $this->assertIsString($paso['valor'], "{$donde}: el valor va como texto");

                $fuente = $paso['fuente'] ?? [];
                $this->assertNotEmpty($fuente['doc'] ?? null, "{$donde}: falta fuente.doc");
                $this->assertMatchesRegularExpression('/^p\. \d+/', $fuente['pagina'] ?? '', "{$donde}: fuente.pagina debe empezar por 'p. N'");
                $this->assertNotEmpty($fuente['url'] ?? null, "{$donde}: falta fuente.url");
                $this->assertTrue($urls->has($fuente['doc']), "{$donde}: {$fuente['doc']} no está en fuentes.json");
                $this->assertSame($urls[$fuente['doc']], $fuente['url'], "{$donde}: la url no es la del PDF fijado");
            }
        }
    }

    public function test_las_lineas_esperadas_son_texto_y_cada_linea_cubierta_tiene_valor(): void
    {
        foreach (CasosIrs::casos(self::ANIO) as $codigo => $caso) {
            $this->assertNotEmpty($caso['lineas'] ?? null, "{$codigo}: faltan las líneas esperadas");

            foreach ($caso['lineas'] as $formulario => $lineas) {
                foreach ($lineas as $linea => $valor) {
                    $this->assertIsString($valor, "{$codigo}: {$formulario}:{$linea} debe ser texto");
                    $this->assertMatchesRegularExpression('/^-?\d+(\.\d+)?$/', $valor, "{$codigo}: {$formulario}:{$linea}");
                }
            }

            $this->assertIsArray($caso['cubre'] ?? null, "{$codigo}: falta cubre");

            foreach ($caso['cubre'] as $cubierta) {
                [$formulario, $linea] = explode(':', $cubierta, 2) + [1 => ''];
                $this->assertArrayHasKey($linea, $caso['lineas'][$formulario] ?? [], "{$codigo}: cubre {$cubierta} sin valor esperado");
            }
        }
    }

    public function test_cada_paso_con_linea_declarada_coincide_con_el_valor_esperado(): void
    {
        foreach (CasosIrs::casos(self::ANIO) as $codigo => $caso) {
            foreach ($caso['pasos'] as $paso) {
                $esperado = $caso['lineas'][$paso['formulario']][$paso['linea']] ?? null;

                if ($esperado !== null) {
                    $this->assertSame($esperado, $paso['valor'], "{$codigo}: {$paso['formulario']}:{$paso['linea']}");
                }
            }
        }
    }

    public function test_los_casos_resuelven_cubiertas_y_mapa_de_cobertura(): void
    {
        $cubiertas = CasosIrs::cubiertas(self::ANIO);

        $this->assertContains('1040:16', $cubiertas);
        $this->assertContains('8962:8a', $cubiertas);
        $this->assertSame($cubiertas, array_keys(CasosIrs::cubrenPorLinea(self::ANIO)));
        $this->assertSame(['pub1040-tax-table-mfj-25300'], CasosIrs::cubrenPorLinea(self::ANIO)['1040:16']);
    }

    public function test_calculo_cobertura_escribe_el_archivo_con_las_lineas_de_los_casos(): void
    {
        $salida = sys_get_temp_dir().'/cobertura-'.uniqid().'.php';

        $this->artisan('calculo:cobertura', ['anio' => self::ANIO, '--salida' => $salida])->assertSuccessful();

        $cobertura = require $salida;
        unlink($salida);

        // Mismo formato que lee CoberturaCasos (6.0): lista plana de "formulario:línea".
        $this->assertSame(CasosIrs::cubiertas(self::ANIO), $cobertura);
        $this->assertContains('1040:16', $cobertura);
    }

    public function test_el_sha256_de_cada_pdf_fijado_coincide(): void
    {
        $fuentes = CasosIrs::fuentes(self::ANIO);
        $this->assertNotEmpty($fuentes['documentos']);

        foreach ($fuentes['documentos'] as $doc) {
            $ruta = CasosIrs::raiz().'/'.self::ANIO."/pdf/{$doc['doc']}.pdf";

            $this->assertFileExists($ruta);
            $this->assertSame($doc['sha256'], hash_file('sha256', $ruta), "SHA-256 de {$doc['doc']}");
            $this->assertSame($doc['bytes'], filesize($ruta), "Tamaño de {$doc['doc']}");
            $this->assertStringStartsWith('https://www.irs.gov/pub/', $doc['url']);
            $this->assertStringContainsString('2025', $doc['ano_verificado'], "{$doc['doc']}: el año no está verificado");
        }
    }

    public function test_los_csv_no_tienen_filas_vacias_y_sus_franjas_son_contiguas_y_crecientes(): void
    {
        $franjas = [
            'tax-table.csv' => ['desde', 'hasta', 2062],
            'eic-table.csv' => ['desde', 'hasta', 1374],
            'f2441-tabla.csv' => ['desde', 'hasta', 16],
        ];

        foreach ($franjas as $archivo => [$desde, $hasta, $esperadas]) {
            $filas = CasosIrs::filasCsv(self::ANIO, $archivo);

            $this->assertCount($esperadas, $filas, "{$archivo}: número de filas");

            $anterior = null;
            foreach ($filas as $i => $fila) {
                foreach ($fila as $columna => $valor) {
                    $esSinLimite = $archivo === 'f2441-tabla.csv' && $columna === 'hasta' && $i === count($filas) - 1;
                    $this->assertTrue($valor !== '' || $esSinLimite, "{$archivo} fila {$i}, columna {$columna} vacía");
                }

                if ($anterior !== null) {
                    $this->assertSame($anterior, $fila[$desde], "{$archivo}: hueco o traslape antes de {$fila[$desde]}");
                }

                if ($fila[$hasta] !== '') {
                    $this->assertGreaterThan((int) $fila[$desde], (int) $fila[$hasta], "{$archivo}: franja invertida en {$fila[$desde]}");
                }

                $anterior = $fila[$hasta];
            }
        }
    }

    public function test_la_tax_table_empieza_en_cero_termina_en_cien_mil_y_no_baja(): void
    {
        $filas = CasosIrs::filasCsv(self::ANIO, 'tax-table.csv');

        $this->assertSame('0', $filas[0]['desde']);
        $this->assertSame('100000', end($filas)['hasta']);

        $anterior = ['soltero' => 0, 'mfj' => 0, 'mfs' => 0, 'hoh' => 0];
        foreach ($filas as $fila) {
            foreach ($anterior as $columna => $valor) {
                $this->assertGreaterThanOrEqual($valor, (int) $fila[$columna], "Tax Table: {$columna} baja en {$fila['desde']}");
                $anterior[$columna] = (int) $fila[$columna];
            }
        }
    }

    public function test_los_dos_ejemplos_de_la_pub_1040_estan_en_los_csv(): void
    {
        $tax = collect(CasosIrs::filasCsv(self::ANIO, 'tax-table.csv'));
        $this->assertSame('2562', $tax->firstWhere('desde', '25300')['mfj']);
        $this->assertSame(['5920', '5526', '5920', '5663'], array_values(collect($tax->firstWhere('desde', '50000'))->only(['soltero', 'mfj', 'mfs', 'hoh'])->all()));

        $eic = collect(CasosIrs::filasCsv(self::ANIO, 'eic-table.csv'));
        $this->assertSame('842', $eic->firstWhere('desde', '2450')['solo_1']);
    }

    public function test_las_celdas_con_asterisco_de_la_eic_tienen_su_nota(): void
    {
        $marcadas = 0;
        foreach (CasosIrs::filasCsv(self::ANIO, 'eic-table.csv') as $fila) {
            foreach ($fila as $columna => $valor) {
                if (str_contains($valor, '*')) {
                    $marcadas++;
                    $nota = collect(CasosIrs::filasCsv(self::ANIO, 'eic-notas.csv'))->first(fn ($n) => $n['desde'] === $fila['desde'] && $n['columna'] === $columna);
                    $this->assertNotNull($nota, "Falta la nota de {$columna} en {$fila['desde']}");
                }
            }
        }

        $this->assertSame(8, $marcadas);
        $this->assertCount(8, CasosIrs::filasCsv(self::ANIO, 'eic-notas.csv'));
    }

    public function test_las_diez_filas_de_control_de_cada_csv_coinciden_con_el_csv(): void
    {
        $revision = collect(CasosIrs::fuentes(self::ANIO)['revision']);
        $porArchivo = $revision->groupBy('archivo');

        foreach (['tax-table.csv', 'eic-table.csv', 'f2441-tabla.csv', 'f8962-pobreza.csv', 'f8962-cifra-aplicable.csv'] as $archivo) {
            $this->assertCount(10, $porArchivo[$archivo], "{$archivo}: deben ser 10 filas de control");
        }

        foreach ($porArchivo as $archivo => $controles) {
            $lineas = array_map('trim', file(CasosIrs::raiz().'/'.self::ANIO."/{$archivo}", FILE_IGNORE_NEW_LINES));

            foreach ($controles as $control) {
                $this->assertContains($control['fila'], $lineas, "{$archivo}: la fila de control {$control['fila']} no está en el CSV");
                $this->assertSame('coincide', $control['resultado']);
                $this->assertGreaterThan(0, $control['pagina']);
            }
        }
    }

    public function test_las_tablas_del_8962_y_del_2441_traen_los_valores_del_pdf(): void
    {
        $cifra = collect(CasosIrs::filasCsv(self::ANIO, 'f8962-cifra-aplicable.csv'));
        $this->assertSame('0.0800', $cifra->firstWhere('desde', '380')['cifra']);
        $this->assertSame('0.0850', $cifra->firstWhere('desde', '400')['cifra']);

        $pobreza = collect(CasosIrs::filasCsv(self::ANIO, 'f8962-pobreza.csv'));
        $this->assertCount(24, $pobreza);
        $this->assertSame('20440', $pobreza->first(fn ($f) => $f['tabla'] === '1-1' && $f['tamano'] === '2')['monto']);

        $this->assertSame(
            [['0', '200', '375', '750'], ['200', '300', '975', '1950'], ['300', '400', '1625', '3250'], ['400', '', '', '']],
            array_map('array_values', CasosIrs::filasCsv(self::ANIO, 'f8962-limite-devolucion.csv')),
        );

        $f2441 = CasosIrs::filasCsv(self::ANIO, 'f2441-tabla.csv');
        $this->assertSame('0.35', $f2441[0]['decimal']);
        $this->assertSame('0.20', end($f2441)['decimal']);
    }
}
