<?php

// Último dólar del tramo de 22 % para jefe de hogar: la fila dice "At least $100,000 but not over $103,350", así que 103.350 todavía va con 22 % menos 6.825.
return [
    'codigo' => 'tax-hoh-tramo-22-borde-103350',
    'anio' => 2025,
    'descripcion' => 'Jefe de hogar con ingreso gravable de USD 103.350: borde superior del tramo de 22 % (22 % menos USD 6.825).',
    'entradas' => [
        'estado_civil' => 'jefe_de_hogar',
        'ingreso_gravable' => '103350',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 103.350.',
            'valor' => '103350',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 de USD 100.000 o más el impuesto no sale de la Tax Table sino de la Tax Computation Worksheet.',
            'valor' => '103350',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con USD 100.000 o más se usa la Tax Computation Worksheet)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'D-a',
            'texto' => 'Fila "At least $100,000 but not over $103,350" de la sección D: columna (a) = línea 15 = USD 103.350.',
            'valor' => '103350',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'D-c',
            'texto' => 'Columna (c) = (a) por (b) = 103.350 por 0.22 = USD 22737.',
            'valor' => '22737',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'D-d',
            'texto' => 'Columna (d), cifra a restar de esa fila: USD 6825.',
            'valor' => '6825',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Tax = (c) menos (d) = 22.737 menos 6.825 = USD 15.912. Es el impuesto de la línea 16.',
            'valor' => '15912',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '15912'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
