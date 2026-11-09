<?php

namespace Tests\Feature\Calculo;

use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * 6.2: pruebas que recorren las tablas completas del IRS contra la calculadora.
 *
 * Están saltadas con motivo: las calculadoras aún no existen y esta subtarea no las lee. Se activan en 6.4
 * (agente principal), cuando exista el motor. La interfaz que esperan, SIN crear ninguna clase:
 *
 *   TablaImpuesto::paraFila(string $estadoCivil, Dinero $ingresoGravable): Dinero
 *       'soltero' | 'mfj' | 'mfs' | 'hoh'; el valor sale de la columna del mismo nombre de tax-table.csv.
 *       Se prueba en el punto medio de cada franja y en desde y hasta - 1 (el hasta es exclusivo).
 *   CreditoEic::paraFila(string $columna, Dinero $montoDeLaHoja): Dinero
 *       $columna = solo_0..solo_3 | mfj_0..mfj_3 de eic-table.csv; las celdas con asterisco se resuelven con eic-notas.csv.
 *   Credito2441::decimalParaLinea7(Dinero $linea7): string
 *       el decimal de f2441-tabla.csv ('0.35' ... '0.20'); "hasta" vacío = sin límite; las franjas son cerradas en "desde" y abiertas en "hasta".
 */
class TablasIrsTest extends TestCase
{
    private const MOTIVO = 'Se activan en 6.4 cuando exista la calculadora (el valor esperado no sale de ella).';

    public function test_la_tax_table_completa_coincide_fila_por_fila(): void
    {
        $this->assertGreaterThan(2000, count(CasosIrs::filasCsv(2025, 'tax-table.csv')));

        $this->markTestSkipped(self::MOTIVO);
    }

    public function test_la_eic_table_completa_coincide_fila_por_fila(): void
    {
        $this->assertGreaterThan(1000, count(CasosIrs::filasCsv(2025, 'eic-table.csv')));

        $this->markTestSkipped(self::MOTIVO);
    }

    public function test_la_tabla_del_2441_coincide_en_cada_escalon(): void
    {
        $this->assertCount(16, CasosIrs::filasCsv(2025, 'f2441-tabla.csv'));

        $this->markTestSkipped(self::MOTIVO);
    }
}
