<?php

namespace Tests\Unit\Support;

use App\Services\Calculo\Nucleo\Dinero;
use App\Support\TablaFiscal;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

class TablaFiscalTest extends TestCase
{
    /**
     * Las tres primeras filas y la última de la tabla de la línea 8 del Form 2441 de 2025.
     */
    private function tabla2441(): TablaFiscal
    {
        return new TablaFiscal([
            ['hasta' => 15000, 'decimal' => '0.35'],
            ['hasta' => 17000, 'decimal' => '0.34'],
            ['hasta' => 19000, 'decimal' => '0.33'],
            ['hasta' => null, 'decimal' => '0.20'],
        ]);
    }

    public function test_un_valor_justo_en_el_borde_cae_en_la_fila_de_abajo(): void
    {
        $this->assertSame('0.35', $this->tabla2441()->buscar(15000)['decimal']);
        $this->assertSame('0.34', $this->tabla2441()->buscar(17000)['decimal']);
    }

    public function test_un_dolar_por_encima_del_borde_cae_en_la_fila_siguiente(): void
    {
        $this->assertSame('0.34', $this->tabla2441()->buscar(15001)['decimal']);
        $this->assertSame('0.33', $this->tabla2441()->buscar(17001)['decimal']);
    }

    public function test_cero_y_negativos_caen_en_la_primera_fila(): void
    {
        $this->assertSame('0.35', $this->tabla2441()->buscar(0)['decimal']);
        $this->assertSame('0.35', $this->tabla2441()->buscar(-500)['decimal']);
    }

    public function test_la_fila_sin_limite_recoge_todo_lo_que_pasa_de_la_ultima(): void
    {
        $this->assertSame('0.20', $this->tabla2441()->buscar(19001)['decimal']);
        $this->assertSame('0.20', $this->tabla2441()->buscar(5_000_000)['decimal']);
    }

    public function test_un_dinero_se_compara_en_centavos(): void
    {
        $this->assertSame('0.35', $this->tabla2441()->buscar(Dinero::deDolares('15000.00'))['decimal']);
        $this->assertSame('0.34', $this->tabla2441()->buscar(Dinero::deDolares('15000.01'))['decimal']);
        $this->assertSame('0.34', $this->tabla2441()->buscar(Dinero::deCentavos(1_700_000))['decimal']);
    }

    public function test_un_entero_y_un_dinero_del_mismo_valor_dan_la_misma_fila(): void
    {
        $this->assertSame(
            $this->tabla2441()->buscar(18000),
            $this->tabla2441()->buscar(Dinero::deDolares(18000)),
        );
    }

    public function test_sin_fila_sin_limite_un_valor_demasiado_alto_no_tiene_fila(): void
    {
        $tabla = new TablaFiscal([['hasta' => 100, 'monto' => 1], ['hasta' => 200, 'monto' => 2]]);

        $this->assertSame(2, $tabla->buscar(200)['monto']);

        $this->expectException(OutOfRangeException::class);
        $tabla->buscar(201);
    }

    public function test_devuelve_la_fila_completa_con_todos_sus_campos(): void
    {
        $tabla = new TablaFiscal([['hasta' => 199, 'soltero' => 375, 'otros' => 750], ['hasta' => null, 'soltero' => null, 'otros' => null]]);

        $this->assertSame(['hasta' => 199, 'soltero' => 375, 'otros' => 750], $tabla->buscar(150));
        $this->assertSame(['hasta' => null, 'soltero' => null, 'otros' => null], $tabla->buscar(400));
        $this->assertCount(2, $tabla->filas());
    }

    public function test_rechaza_una_tabla_vacia(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TablaFiscal([]);
    }

    public function test_rechaza_filas_sin_hasta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TablaFiscal([['decimal' => '0.35']]);
    }

    public function test_rechaza_hasta_que_no_van_en_orden_creciente(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TablaFiscal([['hasta' => 200, 'a' => 1], ['hasta' => 100, 'a' => 2]]);
    }

    public function test_rechaza_hasta_repetidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TablaFiscal([['hasta' => 100, 'a' => 1], ['hasta' => 100, 'a' => 2]]);
    }

    public function test_rechaza_la_fila_sin_limite_que_no_es_la_ultima(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TablaFiscal([['hasta' => null, 'a' => 1], ['hasta' => 100, 'a' => 2]]);
    }

    public function test_rechaza_un_hasta_que_no_es_entero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TablaFiscal([['hasta' => 100.5, 'a' => 1]]);
    }
}
