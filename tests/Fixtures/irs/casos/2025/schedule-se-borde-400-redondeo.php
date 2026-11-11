<?php

// 6.9b. Borde de USD 400 de la línea 4c del Schedule SE donde el redondeo CAMBIA el resultado.
// PENDIENTE DE REVISIÓN (dos lecturas): ganancia de USD 433 → 433 × 0,9235 = 399,8755.
//  - Lectura A (con centavos, sin redondear la línea 4a): 399,88 es menos de 400 → no se debe impuesto de autoempleo (línea 12 = 0).
//  - Lectura B (redondeando cada línea a dólares, i1040gi p. 23 "If you do round to whole dollars, you must round all amounts"):
//    4a = 400 → no es menos de 400 → 10 = 400 × 0,124 = 49,60 → 50; 11 = 400 × 0,029 = 11,60 → 12; 12 = 62; 13 = 31.
// El redondeo es opcional para el contribuyente, pero si se redondea hay que redondear todo; el formulario no dice si el corte de 4c se mira antes o después.
// En "lineas" va la lectura A (como en tax-soltero-ingreso-gravable-100001-borde); la B está en los pasos "-redondeada". Mientras no se decida, sin cobertura.
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-se-borde-400-redondeo',
    'anio' => 2025,
    'descripcion' => 'Soltero con USD 433 de ganancia en el Schedule C: la línea 4a da 399,88; sin redondear no se debe impuesto de autoempleo, y redondeando a dólares (400) se deben USD 62 (pendiente de revisión).',
    'entradas' => [
        'estado_civil' => 'soltero',
        'ganancia_neta_schedule_c_linea_31' => '433',
        'w2_casilla_3_salarios_seguro_social' => '0',
        'usa_metodo_opcional' => 'no',
        'ingreso_de_iglesia_w2' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-se',
            'linea' => '3',
            'texto' => 'Líneas 1a, 1b y 2: ganancia del Schedule C de USD 433.',
            'valor' => '433',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, líneas 2 y 3)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4a',
            'texto' => 'Lectura A: 433 × 0,9235 = 399,8755 → USD 399,88 con centavos. PENDIENTE DE REVISIÓN.',
            'valor' => '399.88',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 4a)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4c',
            'texto' => 'Lectura A: 399,88 es menos de USD 400 → "stop; you don\'t owe self-employment tax". PENDIENTE DE REVISIÓN.',
            'valor' => '399.88',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 4c)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '12',
            'texto' => 'Lectura A: sin impuesto de autoempleo, USD 0. PENDIENTE DE REVISIÓN.',
            'valor' => '0',
            'fuente' => $fuente('i1040sse', 'p. 1 (Who Must File Schedule SE: línea 4c de USD 400 o más)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4a-redondeada',
            'texto' => 'Lectura B: redondeando cada línea a dólares, 399,8755 → USD 400 (de 50 a 99 centavos se sube). PENDIENTE DE REVISIÓN.',
            'valor' => '400',
            'fuente' => $fuente('i1040gi', 'p. 23 (Rounding Off to Whole Dollars)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '10-redondeada',
            'texto' => 'Lectura B: 400 × 12,4 % = 49,60 → USD 50.',
            'valor' => '50',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 10)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '11-redondeada',
            'texto' => 'Lectura B: 400 × 2,9 % = 11,60 → USD 12.',
            'valor' => '12',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 11)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '12-redondeada',
            'texto' => 'Lectura B: 50 + 12 = USD 62 (y la línea 13 = 31). PENDIENTE DE REVISIÓN.',
            'valor' => '62',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, líneas 12 y 13)'),
        ],
    ],
    'lineas' => [
        'sch-se' => ['3' => '433', '4a' => '399.88', '4c' => '399.88', '12' => '0'],
    ],
    'cubre' => [],
    'contraste' => null,
];
