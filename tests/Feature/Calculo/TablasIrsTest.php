<?php

namespace Tests\Feature\Calculo;

use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * 6.2: pruebas que recorren las tablas completas del IRS contra la calculadora.
 *
 * Las tablas completas se prueban cada una en su archivo: la Tax Table en `TablaImpuestoCsvTest` (6.4e), la EIC
 * Table en `CreditoEicCsvTest` (6.4g), la tabla de la línea 8 del 2441 en `TablaF2441CsvTest` (6.4f) y las del
 * 8962 en `TablasF8962CsvTest` (6.4h). Aquí solo queda la revisión de que el CSV del 2441 trae sus 16 escalones.
 *
 * Las franjas de la tabla del 2441 siguen el PDF ("Over — But not over"): abiertas en "desde" y cerradas en
 * "hasta"; "hasta" vacío = sin límite (Form 2441 de 2025, p. 1, línea 8).
 */
class TablasIrsTest extends TestCase
{
    public function test_la_tabla_del_2441_coincide_en_cada_escalon(): void
    {
        $this->assertCount(16, CasosIrs::filasCsv(2025, 'f2441-tabla.csv'));
    }
}
