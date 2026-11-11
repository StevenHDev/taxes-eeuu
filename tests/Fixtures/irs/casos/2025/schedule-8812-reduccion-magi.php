<?php

// 6.9b. Schedule 8812, reducción por ingreso (líneas 9 a 12): jefe de hogar con un hijo menor de 17 con SSN y otro dependiente (su madre).
// Salarios de USD 213.450 y nada más. Línea 10: 213.450 − 200.000 = 13.450, que no es múltiplo de 1.000 → se sube al siguiente: 14.000.
// Impuesto: ingreso gravable = 213.450 − 23.625 = 189.825 → Tax Computation Worksheet, sección D (HOH), fila "Over $103,350 but not over $197,300":
// 189.825 × 0,24 = 45.558,00 − 8.892,00 = 36.666,00 (sin centavos: no hay lectura de redondeo). El impuesto adicional de Medicare de los salarios
// sobre 200.000 va al Schedule 2 parte II (línea 23 del 1040), no a la línea 18, así que no toca la Credit Limit Worksheet A.
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-8812-reduccion-magi',
    'anio' => 2025,
    'descripcion' => 'Jefe de hogar con USD 213.450 de salarios, un hijo de 12 años con SSN y su madre como dependiente: crédito bruto de USD 2.700 reducido en USD 700 (13.450 sube a 14.000 × 5 %), crédito de USD 2.000.',
    'entradas' => [
        'estado_civil' => 'jefe_de_hogar',
        'salarios_w2_casilla_1' => '213450',
        'hijos_menores_de_17_con_ssn' => 1,
        'edades_de_los_hijos' => '12',
        'otros_dependientes' => 1,
        'otros_dependientes_detalle' => 'madre de 70 años, ciudadana, con SSN, sin ingresos',
        'ingreso_excluido_puerto_rico_2555_4563' => '0',
        'detalla_deducciones' => 'no',
        'otros_creditos_schedule_3' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable: 213.450 − 23.625 (deducción de jefe de hogar) = USD 189.825.',
            'valor' => '189825',
            'fuente' => $fuente('f1040', 'p. 2 (líneas 11b a 15; margen: Head of household, $23,625)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Tax Computation Worksheet, sección D, fila de más de 103.350 hasta 197.300: 189.825 × 0,24 = 45.558,00 − 8.892,00 = USD 36.666. Línea 18 = 36.666.',
            'valor' => '36666',
            'fuente' => $fuente('p1040', 'p. 15 (Tax Computation Worksheet, Section D)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '1',
            'texto' => 'Línea 11a del 1040: USD 213.450.',
            'valor' => '213450',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 1)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '3',
            'texto' => 'Líneas 1 y 2d (sin ingresos excluidos): USD 213.450. Es el ingreso ajustado modificado para este crédito.',
            'valor' => '213450',
            'fuente' => $fuente('i1040s8', 'p. 2 (Limits on the CTC and ODC, Modified AGI: "the amount on line 3 of Schedule 8812")'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '4',
            'texto' => 'Hijos menores de 17 con SSN: 1.',
            'valor' => '1',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 4)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '5',
            'texto' => '1 × USD 2.200 = USD 2.200.',
            'valor' => '2200',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 5)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '6',
            'texto' => 'Otros dependientes (la madre): 1.',
            'valor' => '1',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 6)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '7',
            'texto' => '1 × USD 500 = USD 500.',
            'valor' => '500',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 7)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '8',
            'texto' => 'Líneas 5 y 7: 2.200 + 500 = USD 2.700.',
            'valor' => '2700',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 8)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '9',
            'texto' => 'Umbral de los demás estados civiles: USD 200.000.',
            'valor' => '200000',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 9)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '10',
            'texto' => '213.450 − 200.000 = 13.450; no es múltiplo de USD 1.000, se anota el siguiente múltiplo: USD 14.000.',
            'valor' => '14000',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 10: "enter the next multiple of $1,000")'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '11',
            'texto' => '14.000 × 5 % = USD 700.',
            'valor' => '700',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 11)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '12',
            'texto' => '¿Línea 8 (2.700) mayor que la 11 (700)? Sí: 2.700 − 700 = USD 2.000.',
            'valor' => '2000',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 12)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '13',
            'texto' => 'Credit Limit Worksheet A: 1040 línea 18 (36.666) − créditos del Schedule 3 (0) − hoja B (0) = USD 36.666.',
            'valor' => '36666',
            'fuente' => $fuente('i1040s8', 'p. 4 (Credit Limit Worksheet A)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '14',
            'texto' => 'La menor entre 2.000 y 36.666 = USD 2.000.',
            'valor' => '2000',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 14)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '16a',
            'texto' => 'Línea 12 − línea 14 = 0: no hay crédito adicional (ACTC).',
            'valor' => '0',
            'fuente' => $fuente('f1040s8', 'p. 2 (Part II-A, línea 16a)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '19',
            'texto' => 'Crédito por hijos y por otros dependientes: USD 2.000.',
            'valor' => '2000',
            'fuente' => $fuente('f1040', 'p. 2 (línea 19)'),
        ],
    ],
    'lineas' => [
        'sch-8812' => [
            '1' => '213450', '3' => '213450', '4' => '1', '5' => '2200', '6' => '1', '7' => '500', '8' => '2700',
            '9' => '200000', '10' => '14000', '11' => '700', '12' => '2000', '13' => '36666', '14' => '2000', '16a' => '0',
        ],
        '1040' => ['15' => '189825', '16' => '36666', '19' => '2000'],
    ],
    'cubre' => [
        'sch-8812:3', 'sch-8812:5', 'sch-8812:7', 'sch-8812:8', 'sch-8812:9', 'sch-8812:10', 'sch-8812:11',
        'sch-8812:12', 'sch-8812:13', 'sch-8812:14', '1040:16', '1040:19',
    ],
    'contraste' => null,
];
