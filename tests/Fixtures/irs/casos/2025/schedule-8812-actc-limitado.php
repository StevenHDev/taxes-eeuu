<?php

// 6.9b. Schedule 8812, Parte II-A: sin impuesto (ingreso gravable 0), el crédito por hijos sale todo por la parte reembolsable (ACTC),
// limitada por el 15 % del ingreso del trabajo sobre USD 2.500 (línea 20) y no por los USD 1.700 por hijo (línea 16b).
// Jefe de hogar con USD 21.000 de salarios W-2 y dos hijos menores de 17 con SSN. Ingreso del trabajo de la línea 18a = 21.000:
// con la EIC es el de su Step 5 (los salarios) y sin la EIC la Earned Income Worksheet también da la línea 1z (21.000); son iguales.
// La línea 16b (3.400) es menor de USD 5.100: se salta la Parte II-B y la línea 27 es la menor entre las líneas 17 y 20.
// La EIC (1040 línea 27a) sí aplica a esta familia pero no es parte de este caso.
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-8812-actc-limitado',
    'anio' => 2025,
    'descripcion' => 'Jefe de hogar con USD 21.000 de salarios y dos hijos (5 y 8 años) con SSN: no hay impuesto, el crédito no reembolsable es 0 y el reembolsable (ACTC) queda en USD 2.775, el 15 % de lo ganado sobre USD 2.500.',
    'entradas' => [
        'estado_civil' => 'jefe_de_hogar',
        'salarios_w2_casilla_1' => '21000',
        'hijos_menores_de_17_con_ssn' => 2,
        'edades_de_los_hijos' => '5 y 8',
        'otros_dependientes' => 0,
        'pago_de_combate_no_gravable' => '0',
        'ingreso_de_autoempleo' => '0',
        'presenta_form_2555' => 'no',
        'residente_de_puerto_rico' => 'no',
        'detalla_deducciones' => 'no',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable: 21.000 − 23.625 (deducción de jefe de hogar) es menos de cero → 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040', 'p. 2 (línea 15: "If zero or less, enter -0-")'),
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Tax Table, fila 0–5: USD 0. Línea 18 = 0.',
            'valor' => '0',
            'fuente' => $fuente('p1040', 'p. 2 (Tax Table, fila 0–5)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '3',
            'texto' => 'Línea 1 (1040 línea 11a = 21.000) más línea 2d (0): USD 21.000.',
            'valor' => '21000',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, líneas 1 a 3)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '8',
            'texto' => 'Dos hijos × 2.200 (línea 5 = 4.400) más otros dependientes (0): USD 4.400.',
            'valor' => '4400',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, líneas 4 a 8)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '12',
            'texto' => 'Sin reducción (21.000 está muy por debajo de USD 200.000, línea 10 = 0, línea 11 = 0): 4.400 − 0 = USD 4.400.',
            'valor' => '4400',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, líneas 9 a 12)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '13',
            'texto' => 'Credit Limit Worksheet A: 1040 línea 18 (0) − Schedule 3 (0) − hoja B (0) = USD 0.',
            'valor' => '0',
            'fuente' => $fuente('i1040s8', 'p. 4 (Credit Limit Worksheet A)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '14',
            'texto' => 'La menor entre la línea 12 (4.400) y la 13 (0) = USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040s8', 'p. 1 (Part I, línea 14)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '16a',
            'texto' => 'Línea 12 menos línea 14: 4.400 − 0 = USD 4.400.',
            'valor' => '4400',
            'fuente' => $fuente('f1040s8', 'p. 2 (Part II-A, línea 16a)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '16b',
            'texto' => 'Hijos menores de 17 con SSN (los mismos de la línea 4) × USD 1.700: 2 × 1.700 = USD 3.400.',
            'valor' => '3400',
            'fuente' => $fuente('f1040s8', 'p. 2 (Part II-A, línea 16b)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '17',
            'texto' => 'La menor entre la línea 16a (4.400) y la 16b (3.400) = USD 3.400.',
            'valor' => '3400',
            'fuente' => $fuente('f1040s8', 'p. 2 (Part II-A, línea 17)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '18a',
            'texto' => 'Ingreso del trabajo (Earned Income Chart; con o sin EIC da los salarios): USD 21.000.',
            'valor' => '21000',
            'fuente' => $fuente('i1040s8', 'p. 7 (Earned Income Chart — Line 18a) y p. 8 (Earned Income Worksheet, línea 1a)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '19',
            'texto' => '¿Línea 18a mayor de USD 2.500? Sí: 21.000 − 2.500 = USD 18.500.',
            'valor' => '18500',
            'fuente' => $fuente('f1040s8', 'p. 2 (Part II-A, línea 19)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '20',
            'texto' => '18.500 × 15 % = USD 2.775.',
            'valor' => '2775',
            'fuente' => $fuente('f1040s8', 'p. 2 (Part II-A, línea 20)'),
        ],
        [
            'formulario' => 'sch-8812',
            'linea' => '27',
            'texto' => '¿Línea 16b de USD 5.100 o más? No (3.400) y no es residente de Puerto Rico: se salta la Parte II-B y la línea 27 es la menor entre la 17 (3.400) y la 20 (2.775) = USD 2.775.',
            'valor' => '2775',
            'fuente' => $fuente('f1040s8', 'p. 2 (instrucción "Next" de la línea 20 y Part II-C, línea 27)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '19',
            'texto' => 'Crédito no reembolsable del Schedule 8812 (línea 14): USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040', 'p. 2 (línea 19)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '28',
            'texto' => 'Crédito adicional por hijos (ACTC) del Schedule 8812, línea 27: USD 2.775.',
            'valor' => '2775',
            'fuente' => $fuente('f1040', 'p. 2 (línea 28)'),
        ],
    ],
    'lineas' => [
        'sch-8812' => [
            '3' => '21000', '8' => '4400', '12' => '4400', '13' => '0', '14' => '0', '16a' => '4400', '16b' => '3400',
            '17' => '3400', '18a' => '21000', '19' => '18500', '20' => '2775', '27' => '2775',
        ],
        '1040' => ['15' => '0', '16' => '0', '19' => '0', '28' => '2775'],
    ],
    'cubre' => [
        'sch-8812:13', 'sch-8812:14', 'sch-8812:16a', 'sch-8812:16b', 'sch-8812:17', 'sch-8812:18a',
        'sch-8812:19', 'sch-8812:20', 'sch-8812:27', '1040:19', '1040:28',
    ],
    'contraste' => null,
];
