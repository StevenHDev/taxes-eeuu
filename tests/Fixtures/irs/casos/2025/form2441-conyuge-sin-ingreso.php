<?php

// 6.9b. Form 2441 con un cónyuge sin ingreso del trabajo que NO fue estudiante de tiempo completo ni estuvo incapacitado:
// la línea 5 es 0, la línea 6 (la menor de 3, 4 y 5) es 0 y el crédito es 0 aunque haya gastos de guardería.
// Si la cónyuge hubiera sido estudiante o incapacitada se le contarían USD 250 por mes (USD 500 con dos personas) (i2441 p. 4); no es el caso.
// Impuesto: ingreso gravable 45.000 − 31.500 = 13.500 → Tax Table (MFJ) = 1.353 (p1040 p. 4).
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'form2441-conyuge-sin-ingreso',
    'anio' => 2025,
    'descripcion' => 'Matrimonio en conjunto: él gana USD 45.000 y ella no trabajó ni estudió ni estuvo incapacitada; pagaron USD 3.500 de guardería para su hijo de 4 años. La línea 5 es 0 y el crédito por cuidado es USD 0.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'salarios_w2_contribuyente' => '45000',
        'salarios_w2_conyuge' => '0',
        'ingreso_autoempleo_conyuge' => '0',
        'conyuge_estudiante_tiempo_completo' => 'no',
        'conyuge_incapacitada' => 'no',
        'personas_cuidadas' => [
            ['edad' => 4, 'gastos_pagados_2025' => '3500'],
        ],
        'w2_casilla_10_beneficios_cuidado' => '0',
        'gastos_2024_pagados_en_2025' => '0',
        'detalla_deducciones' => 'no',
    ],
    'pasos' => [
        [
            'formulario' => 'f2441',
            'linea' => '3',
            'texto' => 'Gastos de la columna (d): 3.500; con una sola persona no se pasa de USD 3.000 → 3.000.',
            'valor' => '3000',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 3)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '4',
            'texto' => 'Ingreso del trabajo del contribuyente: USD 45.000.',
            'valor' => '45000',
            'fuente' => $fuente('i2441', 'p. 4 (Lines 4 and 5)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '5',
            'texto' => 'Ingreso del trabajo de la cónyuge: USD 0 (no trabajó, no fue estudiante de tiempo completo ni estuvo incapacitada, así que no hay ingreso supuesto de USD 250 o 500 al mes).',
            'valor' => '0',
            'fuente' => $fuente('i2441', 'p. 4 (Lines 4 and 5; If You or Your Spouse Was a Student or Disabled)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '6',
            'texto' => 'La menor entre las líneas 3 (3.000), 4 (45.000) y 5 (0) = USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 6)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '7',
            'texto' => 'Línea 11a del 1040: USD 45.000.',
            'valor' => '45000',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 7)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '8',
            'texto' => 'Tabla de la línea 8: más de 43.000 → 0.20.',
            'valor' => '0.20',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, tabla de la línea 8)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '9a',
            'texto' => 'Línea 6 por la línea 8: 0 × 0,20 = USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 9a)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '9c',
            'texto' => 'Líneas 9a y 9b: USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, líneas 9b y 9c)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '10',
            'texto' => 'Credit Limit Worksheet: 1040 línea 18 = Tax Table de 13.500 (fila 13.500–13.550, MFJ) = 1.353, menos Schedule 3 líneas 1 y 6l (0) = USD 1.353.',
            'valor' => '1353',
            'fuente' => $fuente('i2441', 'p. 5 (Line 10, Credit Limit Worksheet)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '11',
            'texto' => 'La menor entre la línea 9c (0) y la 10 (1.353) = USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 11)'),
        ],
        [
            'formulario' => 'sch-3',
            'linea' => '2',
            'texto' => 'Crédito por cuidado de hijos y dependientes: USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040s3', 'p. 1 (línea 2)'),
        ],
    ],
    'lineas' => [
        'f2441' => ['3' => '3000', '4' => '45000', '5' => '0', '6' => '0', '7' => '45000', '8' => '0.20', '9a' => '0', '9c' => '0', '10' => '1353', '11' => '0'],
        'sch-3' => ['2' => '0'],
    ],
    'cubre' => ['f2441:3', 'f2441:5', 'f2441:6', 'f2441:9a', 'f2441:11', 'sch-3:2'],
    'contraste' => null,
];
