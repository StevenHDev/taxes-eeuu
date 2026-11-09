<?php

// Declaración conjunta con los dos cónyuges educadores elegibles: "the maximum deduction is $600. However, neither spouse can deduct more than $300 of their qualified expenses" (p. 93).
// Cada cónyuge se limita a USD 300 de sus propios gastos: 300 + 200 = 500. No se suman los gastos y se topan en 600 (eso daría 600).
return [
    'codigo' => 'schedule1-educador-tope-mfj-ambos',
    'anio' => 2025,
    'descripcion' => 'Matrimonio en conjunto con los dos cónyuges maestros elegibles: uno pagó USD 450 y el otro USD 200; la línea 11 es USD 500 (300 del primero y 200 del segundo).',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'educadores_elegibles' => 2,
        'gastos_calificados_conyuge_1' => '450',
        'gastos_calificados_conyuge_2' => '200',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-1',
            'linea' => '11-conyuge-1',
            'texto' => 'Cónyuge 1: gastos de USD 450; no puede deducir más de USD 300 de sus gastos: USD 300.',
            'valor' => '300',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 93 (Line 11, Educator Expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '11-conyuge-2',
            'texto' => 'Cónyuge 2: gastos de USD 200, por debajo de USD 300: USD 200.',
            'valor' => '200',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 93 (Line 11, Educator Expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '11',
            'texto' => 'Línea 11 = 300 + 200 = USD 500 (el máximo conjunto sería USD 600).',
            'valor' => '500',
            'fuente' => ['doc' => 'f1040s1', 'pagina' => 'p. 2 (línea 11, Educator expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040s1--2025.pdf'],
        ],
    ],
    'lineas' => [
        'sch-1' => ['11' => '500'],
    ],
    'cubre' => ['sch-1:11'],
    'contraste' => null,
];
