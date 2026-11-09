<?php

// Gastos de educador (Schedule 1, línea 11): las instrucciones del 1040 (p. 93) dicen "up to $300 of qualified expenses". Con USD 450 pagados el tope manda.
return [
    'codigo' => 'schedule1-educador-tope',
    'anio' => 2025,
    'descripcion' => 'Maestra soltera de primaria (más de 900 horas en una escuela) con USD 450 de gastos calificados: la línea 11 del Schedule 1 queda en el tope de USD 300.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'educador_elegible' => 'si',
        'gastos_calificados_pagados' => '450',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-1',
            'linea' => '11-gastos',
            'texto' => 'Gastos calificados pagados en 2025 y no reembolsados: USD 450 (ya descontados reembolsos y beneficios exentos).',
            'valor' => '450',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 93 (Line 11, Educator Expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '11-tope',
            'texto' => 'Educador elegible (K-12, al menos 900 horas en un año escolar): puede deducir hasta USD 300 de gastos calificados.',
            'valor' => '300',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 93 (Line 11, Educator Expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '11',
            'texto' => 'Línea 11 = el menor entre 450 y 300 = USD 300.',
            'valor' => '300',
            'fuente' => ['doc' => 'f1040s1', 'pagina' => 'p. 2 (línea 11, Educator expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040s1--2025.pdf'],
        ],
    ],
    'lineas' => [
        'sch-1' => ['11' => '300'],
    ],
    'cubre' => ['sch-1:11'],
    'contraste' => null,
];
