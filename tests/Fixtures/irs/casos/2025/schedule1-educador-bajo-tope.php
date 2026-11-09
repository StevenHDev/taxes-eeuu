<?php

// Gastos de educador por debajo del tope: se deduce lo gastado.
return [
    'codigo' => 'schedule1-educador-bajo-tope',
    'anio' => 2025,
    'descripcion' => 'Maestra soltera de primaria con USD 250 de gastos calificados: la línea 11 del Schedule 1 es lo gastado, USD 250 (menos del tope de USD 300).',
    'entradas' => [
        'estado_civil' => 'soltero',
        'educador_elegible' => 'si',
        'gastos_calificados_pagados' => '250',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-1',
            'linea' => '11-gastos',
            'texto' => 'Gastos calificados pagados: USD 250.',
            'valor' => '250',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 93 (Line 11, Educator Expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '11-tope',
            'texto' => 'Tope por educador elegible: USD 300.',
            'valor' => '300',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 93 (Line 11, Educator Expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '11',
            'texto' => 'Línea 11 = el menor entre 250 y 300 = USD 250.',
            'valor' => '250',
            'fuente' => ['doc' => 'f1040s1', 'pagina' => 'p. 2 (línea 11, Educator expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040s1--2025.pdf'],
        ],
    ],
    'lineas' => [
        'sch-1' => ['11' => '250'],
    ],
    'cubre' => ['sch-1:11'],
    'contraste' => null,
];
