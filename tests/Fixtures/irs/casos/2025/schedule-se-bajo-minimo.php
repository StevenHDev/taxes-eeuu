<?php

// 6.9b. Schedule SE por debajo del mínimo de USD 400 (línea 4c). Ganancia neta del Schedule C de USD 432:
// 432 × 0,9235 = 398,952. Con centavos es 398,95 y redondeando a dólares (i1040gi p. 23, Rounding Off to Whole Dollars) es 399;
// en las dos lecturas la línea 4c es menor de 400, así que no se debe impuesto de autoempleo y no hay deducción de la mitad.
// Convención de este lote: las líneas van en dólares enteros (redondeo de la p. 23), y solo se declara cobertura cuando llevar los
// centavos hasta el final da el mismo dólar entero en cada línea. El borde que sí cambia con el redondeo está en schedule-se-borde-400-redondeo.
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-se-bajo-minimo',
    'anio' => 2025,
    'descripcion' => 'Soltero con USD 432 de ganancia neta en el Schedule C y sin salarios: la línea 4c del Schedule SE da USD 399 (menos de 400) y no se debe impuesto de autoempleo.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'ganancia_neta_schedule_c_linea_31' => '432',
        'ganancia_agricola' => '0',
        'ingreso_de_iglesia_w2' => '0',
        'usa_metodo_opcional' => 'no',
        'salarios_seguro_social_w2' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-se',
            'linea' => '2',
            'texto' => 'Ganancia neta del Schedule C, línea 31: USD 432.',
            'valor' => '432',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 2)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '3',
            'texto' => 'Suma de las líneas 1a, 1b y 2 (sin finca): USD 432.',
            'valor' => '432',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 3)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4a',
            'texto' => 'Línea 3 por 92,35 %: 432 × 0,9235 = 398,952 → USD 399 redondeado (398,95 con centavos).',
            'valor' => '399',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 4a)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4c',
            'texto' => 'Líneas 4a y 4b (sin métodos opcionales): USD 399. Es menos de USD 400: "stop; you don\'t owe self-employment tax".',
            'valor' => '399',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 4c)'),
        ],
        [
            'formulario' => 'sch-se',
            'linea' => '4c-minimo',
            'texto' => 'Solo se presenta el Schedule SE si la línea 4c es USD 400 o más (o hay ingreso de iglesia de USD 108,28 o más).',
            'valor' => '400',
            'fuente' => $fuente('i1040sse', 'p. 1 (Who Must File Schedule SE)'),
        ],
        [
            'formulario' => 'sch-2',
            'linea' => '4',
            'texto' => 'Impuesto de autoempleo en el Schedule 2, línea 4: USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040s2', 'p. 1 (línea 4, Self-employment tax)'),
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '15',
            'texto' => 'Deducción de la mitad del impuesto de autoempleo (Schedule 1, línea 15): USD 0, porque no hay impuesto.',
            'valor' => '0',
            'fuente' => $fuente('f1040s1', 'p. 2 (línea 15, Deductible part of self-employment tax)'),
        ],
    ],
    'lineas' => [
        'sch-se' => ['2' => '432', '3' => '432', '4a' => '399', '4c' => '399'],
        'sch-2' => ['4' => '0'],
        'sch-1' => ['15' => '0'],
    ],
    'cubre' => ['sch-se:2', 'sch-se:3', 'sch-se:4a', 'sch-se:4c', 'sch-2:4', 'sch-1:15'],
    'contraste' => null,
];
