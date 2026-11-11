<?php

// 6.9b. Schedule SE con salarios W-2 que se comen casi todo el tope del Seguro Social (línea 8a): la línea 10 usa la línea 9 (menor que la 6).
// Ganancia del Schedule C de USD 28.000 y salarios del Seguro Social (W-2 casilla 3) de USD 151.000; sin propinas (casilla 7), sin Form 4137 ni 8919.
// Redondeo: la convención del lote (líneas en dólares enteros, i1040gi p. 23). Con centavos: 4a = 25.858,00; 10 = 3.112,40; 11 = 749,882; 12 = 3.862,282; 13 = 1.931,141.
// Redondeando línea por línea: 10 = 3.112, 11 = 750, 12 = 3.862, 13 = 1.931. Las dos lecturas dan el mismo dólar entero en cada línea.
// Salarios + autoempleo = 151.000 + 25.858 = 176.858, menos de USD 200.000: no hay impuesto adicional de Medicare (Form 8959) en este caso.
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-se-con-w2',
    'anio' => 2025,
    'descripcion' => 'Soltero con USD 151.000 de salarios del Seguro Social en su W-2 y USD 28.000 de ganancia en el Schedule C: solo USD 25.100 quedan bajo el tope de USD 176.100, y el impuesto de autoempleo es USD 3.862.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'ganancia_neta_schedule_c_linea_31' => '28000',
        'w2_casilla_3_salarios_seguro_social' => '151000',
        'w2_casilla_7_propinas_seguro_social' => '0',
        'form_4137_o_8919' => 'no',
        'usa_metodo_opcional' => 'no',
        'ingreso_de_iglesia_w2' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-se',
            'linea' => '2',
            'texto' => 'Ganancia neta del Schedule C, línea 31: USD 28.000.',
            'valor' => '28000',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 2)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '3',
            'texto' => 'Suma de las líneas 1a, 1b y 2: USD 28.000.',
            'valor' => '28000',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 3)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4a',
            'texto' => 'Línea 3 por 92,35 %: 28.000 × 0,9235 = USD 25.858 (exacto).',
            'valor' => '25858',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 4a)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4c',
            'texto' => 'Líneas 4a y 4b (sin métodos opcionales): USD 25.858; es USD 400 o más, se sigue.',
            'valor' => '25858',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 4c)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '6',
            'texto' => 'Líneas 4c y 5b (sin ingreso de iglesia): USD 25.858.',
            'valor' => '25858',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 6)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '7',
            'texto' => 'Tope de salarios y ganancias sujetos al Seguro Social en 2025: USD 176.100.',
            'valor' => '176100',
            'fuente' => $fuente('i1040sse', 'p. 1 (What\'s New, Maximum income subject to social security tax)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '8a',
            'texto' => 'Salarios y propinas del Seguro Social (W-2 casillas 3 y 7): 151.000 + 0 = USD 151.000. Es menos de 176.100, así que se siguen las líneas 8b a 10.',
            'valor' => '151000',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 8a)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '8d',
            'texto' => 'Suma de las líneas 8a, 8b y 8c (sin Form 4137 ni 8919): USD 151.000.',
            'valor' => '151000',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 8d)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '9',
            'texto' => 'Línea 7 menos línea 8d: 176.100 − 151.000 = USD 25.100.',
            'valor' => '25100',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 9)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '10',
            'texto' => 'La menor entre la línea 6 (25.858) y la 9 (25.100), por 12,4 %: 25.100 × 0,124 = 3.112,40 → USD 3.112.',
            'valor' => '3112',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 10)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '11',
            'texto' => 'Línea 6 por 2,9 %: 25.858 × 0,029 = 749,882 → USD 750.',
            'valor' => '750',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 11)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '12',
            'texto' => 'Impuesto de autoempleo: líneas 10 y 11 = 3.112 + 750 = USD 3.862 (con centavos 3.862,28). Va al Schedule 2, línea 4.',
            'valor' => '3862',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 12)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '13',
            'texto' => 'Mitad deducible: 3.862 × 50 % = USD 1.931 (con centavos 1.931,14). Va al Schedule 1, línea 15.',
            'valor' => '1931',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 13)'),
        ],
        [
            'formulario' => 'sch-2',
            'linea' => '4',
            'texto' => 'Impuesto de autoempleo en el Schedule 2, línea 4: USD 3.862.',
            'valor' => '3862',
            'fuente' => $fuente('f1040s2', 'p. 1 (línea 4, Self-employment tax)'),
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '15',
            'texto' => 'Parte deducible del impuesto de autoempleo en el Schedule 1, línea 15: USD 1.931.',
            'valor' => '1931',
            'fuente' => $fuente('f1040s1', 'p. 2 (línea 15, Deductible part of self-employment tax)'),
        ],
    ],
    'lineas' => [
        'sch-se' => [
            '2' => '28000', '3' => '28000', '4a' => '25858', '4c' => '25858', '6' => '25858', '7' => '176100',
            '8a' => '151000', '8d' => '151000', '9' => '25100', '10' => '3112', '11' => '750', '12' => '3862', '13' => '1931',
        ],
        'sch-2' => ['4' => '3862'],
        'sch-1' => ['15' => '1931'],
    ],
    'cubre' => [
        'sch-se:2', 'sch-se:3', 'sch-se:4a', 'sch-se:4c', 'sch-se:6', 'sch-se:8a', 'sch-se:9',
        'sch-se:10', 'sch-se:11', 'sch-se:12', 'sch-se:13', 'sch-2:4', 'sch-1:15',
    ],
    'contraste' => null,
];
