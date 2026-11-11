<?php

// 6.9b. Schedule 8812, Parte I: matrimonio en conjunto con dos hijos menores de 17 con SSN válido, sin reducción por ingreso y con impuesto suficiente.
// Salarios W-2 de USD 90.000 entre los dos y nada más. Deducción estándar USD 31.500 → ingreso gravable 58.500 → Tax Table (MFJ) = 6.546 (p1040 p. 9).
// Sin créditos del Schedule 3 ni Credit Limit Worksheet B (no hay 8396, 8839, 5695 Parte I ni 8859). Con 90.000 no hay EIC (tope con 2 hijos en conjunto: 64.430).
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-8812-dos-hijos',
    'anio' => 2025,
    'descripcion' => 'Matrimonio en conjunto con USD 90.000 de salarios y dos hijos (6 y 10 años) con SSN válido: crédito por hijos de USD 4.400 en la línea 14 del 8812 y la 19 del 1040, sin parte reembolsable.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'salarios_w2_casilla_1' => '90000',
        'hijos_menores_de_17_con_ssn' => 2,
        'edades_de_los_hijos' => '6 y 10',
        'otros_dependientes' => 0,
        'ingreso_excluido_puerto_rico_2555_4563' => '0',
        'detalla_deducciones' => 'no',
        'otros_creditos_schedule_3' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable: 11a (90.000) − 12e (31.500) = USD 58.500.',
            'valor' => '58500',
            'fuente' => $fuente('f1040', 'p. 2 (líneas 11b a 15; deducción de casados en conjunto USD 31.500 en el margen)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Tax Table, fila 58.500–58.550, columna "Married filing jointly": USD 6.546. Sin Schedule 2 parte I, la línea 18 = 6.546.',
            'valor' => '6546',
            'fuente' => $fuente('p1040', 'p. 9 (Tax Table, fila 58,500–58,550)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '1',
            'texto' => 'Línea 11a del 1040: USD 90.000.',
            'valor' => '90000',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 1)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '3',
            'texto' => 'Líneas 1 y 2d (sin ingresos excluidos): USD 90.000.',
            'valor' => '90000',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, líneas 2a a 3)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '4',
            'texto' => 'Hijos menores de 17 con el SSN requerido: 2.',
            'valor' => '2',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 4)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '5',
            'texto' => 'Línea 4 por USD 2.200: 2 × 2.200 = USD 4.400.',
            'valor' => '4400',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 5)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '6',
            'texto' => 'Otros dependientes: 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 6)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '7',
            'texto' => 'Línea 6 por USD 500: 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 7)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '8',
            'texto' => 'Líneas 5 y 7: USD 4.400.',
            'valor' => '4400',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 8)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '9',
            'texto' => 'Umbral de casados en conjunto: USD 400.000.',
            'valor' => '400000',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 9)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '10',
            'texto' => 'Línea 3 menos línea 9: 90.000 − 400.000 es cero o menos → 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 10)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '11',
            'texto' => 'Línea 10 por 5 %: 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 11)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '12',
            'texto' => '¿Línea 8 mayor que la 11? Sí: 4.400 − 0 = USD 4.400.',
            'valor' => '4400',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 12)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '13',
            'texto' => 'Credit Limit Worksheet A: línea 1 = 1040 línea 18 (6.546); línea 2 = créditos del Schedule 3 (0); línea 3 = 6.546; línea 4 = 0 (sin hoja B); línea 5 = USD 6.546.',
            'valor' => '6546',
            'fuente' => $fuente('i1040s8', 'p. 4 (Credit Limit Worksheet A)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '14',
            'texto' => 'La menor entre la línea 12 (4.400) y la 13 (6.546) = USD 4.400. Va a la línea 19 del 1040.',
            'valor' => '4400',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 14)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '16a',
            'texto' => 'Línea 12 menos línea 14 = 0: "If zero, stop here; you cannot take the additional child tax credit".',
            'valor' => '0',
            'fuente' => $fuente('f1040s8', 'p. 2 (Part II-A, línea 16a)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '19',
            'texto' => 'Crédito por hijos y por otros dependientes del Schedule 8812: USD 4.400.',
            'valor' => '4400',
            'fuente' => $fuente('f1040', 'p. 2 (línea 19)'),
        ],
    ],
    'lineas' => [
        'sch-8812' => [
            '1' => '90000', '3' => '90000', '4' => '2', '5' => '4400', '6' => '0', '7' => '0', '8' => '4400',
            '9' => '400000', '10' => '0', '11' => '0', '12' => '4400', '13' => '6546', '14' => '4400', '16a' => '0',
        ],
        '1040' => ['15' => '58500', '16' => '6546', '19' => '4400'],
    ],
    'cubre' => [
        'sch-8812:1', 'sch-8812:3', 'sch-8812:4', 'sch-8812:5', 'sch-8812:6', 'sch-8812:7', 'sch-8812:8',
        'sch-8812:9', 'sch-8812:10', 'sch-8812:11', 'sch-8812:12', 'sch-8812:13', 'sch-8812:14', 'sch-8812:16a', '1040:19',
    ],
    'contraste' => null,
];
