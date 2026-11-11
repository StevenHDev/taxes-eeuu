<?php

// 6.9b. Form 2441, Parte II, matrimonio en conjunto con dos personas cuidadas: el tope de USD 6.000 recorta los gastos (7.500) y luego
// manda el ingreso del trabajo del cónyuge que gana menos (5.000, línea 5). Sin beneficios del empleador (W-2 casilla 10 = 0, sin Parte III)
// y sin gastos de 2024 pagados en 2025 (línea 9b = 0).
// Impuesto para la Credit Limit Worksheet: ingreso gravable 57.000 − 31.500 = 25.500 → Tax Table (MFJ) = 2.586 (p1040 p. 5).
// El crédito por hijos del 8812 va en la línea 19 del 1040 y no entra en el límite del 2441 (la hoja solo resta el Schedule 3 líneas 1 y 6l).
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'form2441-dos-personas-mfj',
    'anio' => 2025,
    'descripcion' => 'Matrimonio en conjunto (él gana USD 52.000 y ella USD 5.000) que pagó USD 7.500 de guardería para dos hijos de 3 y 6 años: los gastos se cortan en 6.000, luego en los 5.000 de ella, y el crédito es 5.000 × 20 % = USD 1.000.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'salarios_w2_contribuyente' => '52000',
        'salarios_w2_conyuge' => '5000',
        'otros_ingresos' => '0',
        'personas_cuidadas' => [
            ['edad' => 3, 'gastos_pagados_2025' => '4500'],
            ['edad' => 6, 'gastos_pagados_2025' => '3000'],
        ],
        'proveedor' => 'guardería con EIN, no es pariente',
        'w2_casilla_10_beneficios_cuidado' => '0',
        'gastos_2024_pagados_en_2025' => '0',
        'estudiante_o_incapacitado' => 'ninguno',
        'detalla_deducciones' => 'no',
        'otros_creditos_schedule_3' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'f2441',
            'linea' => '3',
            'texto' => 'Suma de la columna (d) de la línea 2: 4.500 + 3.000 = 7.500; con dos o más personas no se pasa de USD 6.000 → 6.000.',
            'valor' => '6000',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 3)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '4',
            'texto' => 'Ingreso del trabajo del contribuyente (su parte de la línea 1z): USD 52.000.',
            'valor' => '52000',
            'fuente' => $fuente('i2441', 'p. 4 (Lines 4 and 5)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '5',
            'texto' => 'Ingreso del trabajo de la cónyuge (declaración conjunta, ninguno estudiante ni incapacitado): USD 5.000.',
            'valor' => '5000',
            'fuente' => $fuente('i2441', 'p. 4 (Lines 4 and 5)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '6',
            'texto' => 'La menor entre las líneas 3 (6.000), 4 (52.000) y 5 (5.000) = USD 5.000.',
            'valor' => '5000',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 6)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '7',
            'texto' => 'Línea 11a del 1040: 52.000 + 5.000 = USD 57.000.',
            'valor' => '57000',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 7)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '8',
            'texto' => 'Tabla de la línea 8: más de 43.000, sin límite → 0.20.',
            'valor' => '0.20',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, tabla de la línea 8)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '9a',
            'texto' => 'Línea 6 por la línea 8: 5.000 × 0,20 = USD 1.000.',
            'valor' => '1000',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 9a)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '9c',
            'texto' => 'Líneas 9a y 9b (sin gastos de 2024): USD 1.000.',
            'valor' => '1000',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, líneas 9b y 9c)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '18',
            'texto' => 'Ingreso gravable 57.000 − 31.500 = 25.500; Tax Table, fila 25.500–25.550, columna "Married filing jointly": USD 2.586. Sin Schedule 2 parte I, línea 18 = 2.586.',
            'valor' => '2586',
            'fuente' => $fuente('p1040', 'p. 5 (Tax Table, fila 25,500–25,550)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '10',
            'texto' => 'Credit Limit Worksheet: 1040 línea 18 (2.586) − Schedule 3 líneas 1 y 6l (0) = USD 2.586.',
            'valor' => '2586',
            'fuente' => $fuente('i2441', 'p. 5 (Line 10, Credit Limit Worksheet)'),
        ],
        [
            'formulario' => 'f2441',
            'linea' => '11',
            'texto' => 'La menor entre la línea 9c (1.000) y la 10 (2.586) = USD 1.000. Va al Schedule 3, línea 2.',
            'valor' => '1000',
            'fuente' => $fuente('f2441', 'p. 1 (Part II, línea 11)'),
        ],
        [
            'formulario' => 'sch-3',
            'linea' => '2',
            'texto' => 'Crédito por cuidado de hijos y dependientes, del Form 2441 línea 11: USD 1.000.',
            'valor' => '1000',
            'fuente' => $fuente('f1040s3', 'p. 1 (línea 2)'),
        ],
        [
            'formulario' => 'sch-3',
            'linea' => '8',
            'texto' => 'Suma de las líneas 1 a 4, 5a, 5b y 7 (solo el 2441): USD 1.000. Va a la línea 20 del 1040.',
            'valor' => '1000',
            'fuente' => $fuente('f1040s3', 'p. 1 (línea 8)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '20',
            'texto' => 'Monto del Schedule 3, línea 8: USD 1.000.',
            'valor' => '1000',
            'fuente' => $fuente('f1040', 'p. 2 (línea 20)'),
        ],
    ],
    'lineas' => [
        'f2441' => ['3' => '6000', '4' => '52000', '5' => '5000', '6' => '5000', '7' => '57000', '8' => '0.20', '9a' => '1000', '9c' => '1000', '10' => '2586', '11' => '1000'],
        'sch-3' => ['2' => '1000', '8' => '1000'],
        '1040' => ['18' => '2586', '20' => '1000'],
    ],
    'cubre' => [
        'f2441:3', 'f2441:4', 'f2441:5', 'f2441:6', 'f2441:7', 'f2441:8', 'f2441:9a', 'f2441:9c', 'f2441:10', 'f2441:11',
        'sch-3:2', 'sch-3:8', '1040:20',
    ],
    'contraste' => null,
];
