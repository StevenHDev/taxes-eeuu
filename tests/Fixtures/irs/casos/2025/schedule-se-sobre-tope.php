<?php

// 6.9b. Schedule SE por encima del tope del Seguro Social, sin salarios W-2: la línea 6 (186.547) pasa de USD 176.100, así que la línea 10 usa la 9 (= 176.100).
// Redondeo (convención del lote, i1040gi p. 23): con centavos 10 = 21.836,40; 11 = 5.409,863; 12 = 27.246,263; 13 = 13.623,1315.
// Redondeando línea por línea: 10 = 21.836, 11 = 5.410, 12 = 27.246, 13 = 13.623. Mismo dólar entero en las dos lecturas.
// La línea 6 (186.547) es menor de USD 200.000 (umbral de soltero del Form 8959): no hay impuesto adicional de Medicare.
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-se-sobre-tope',
    'anio' => 2025,
    'descripcion' => 'Soltero sin salarios con USD 202.000 de ganancia en el Schedule C: la parte del Seguro Social se corta en el tope de USD 176.100 y el impuesto de autoempleo es USD 27.246.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'ganancia_neta_schedule_c_linea_31' => '202000',
        'w2_casilla_3_salarios_seguro_social' => '0',
        'w2_casilla_7_propinas_seguro_social' => '0',
        'usa_metodo_opcional' => 'no',
        'ingreso_de_iglesia_w2' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-se',
            'linea' => '2',
            'texto' => 'Ganancia neta del Schedule C, línea 31: USD 202.000.',
            'valor' => '202000',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 2)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '3',
            'texto' => 'Suma de las líneas 1a, 1b y 2: USD 202.000.',
            'valor' => '202000',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 3)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4a',
            'texto' => 'Línea 3 por 92,35 %: 202.000 × 0,9235 = USD 186.547 (exacto).',
            'valor' => '186547',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 4a)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4c',
            'texto' => 'Líneas 4a y 4b: USD 186.547.',
            'valor' => '186547',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 4c)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '6',
            'texto' => 'Líneas 4c y 5b: USD 186.547.',
            'valor' => '186547',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 6)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '7',
            'texto' => 'Tope del Seguro Social en 2025: USD 176.100.',
            'valor' => '176100',
            'fuente' => $fuente('i1040sse', 'p. 1 (What\'s New, Maximum income subject to social security tax)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '8a',
            'texto' => 'Salarios y propinas del Seguro Social (W-2 casillas 3 y 7): USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 8a)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '9',
            'texto' => 'Línea 7 menos línea 8d: 176.100 − 0 = USD 176.100.',
            'valor' => '176100',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 9)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '10',
            'texto' => 'La menor entre la línea 6 (186.547) y la 9 (176.100), por 12,4 %: 176.100 × 0,124 = 21.836,40 → USD 21.836.',
            'valor' => '21836',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 10)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '11',
            'texto' => 'Línea 6 por 2,9 % (Medicare no tiene tope): 186.547 × 0,029 = 5.409,863 → USD 5.410.',
            'valor' => '5410',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 11)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '12',
            'texto' => 'Impuesto de autoempleo: 21.836 + 5.410 = USD 27.246 (con centavos 27.246,26).',
            'valor' => '27246',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 12)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '13',
            'texto' => 'Mitad deducible: 27.246 × 50 % = USD 13.623.',
            'valor' => '13623',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 13)'),
        ],
        [
            'formulario' => 'sch-2',
            'linea' => '4',
            'texto' => 'Schedule 2, línea 4: USD 27.246.',
            'valor' => '27246',
            'fuente' => $fuente('f1040s2', 'p. 1 (línea 4, Self-employment tax)'),
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '15',
            'texto' => 'Schedule 1, línea 15: USD 13.623.',
            'valor' => '13623',
            'fuente' => $fuente('f1040s1', 'p. 2 (línea 15, Deductible part of self-employment tax)'),
        ],
    ],
    'lineas' => [
        'sch-se' => [
            '2' => '202000', '3' => '202000', '4a' => '186547', '4c' => '186547', '6' => '186547', '7' => '176100',
            '8a' => '0', '9' => '176100', '10' => '21836', '11' => '5410', '12' => '27246', '13' => '13623',
        ],
        'sch-2' => ['4' => '27246'],
        'sch-1' => ['15' => '13623'],
    ],
    'cubre' => [
        'sch-se:4a', 'sch-se:4c', 'sch-se:6', 'sch-se:9', 'sch-se:10', 'sch-se:11', 'sch-se:12', 'sch-se:13',
        'sch-2:4', 'sch-1:15',
    ],
    'contraste' => null,
];
