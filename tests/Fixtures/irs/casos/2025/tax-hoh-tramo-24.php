<?php

// Cambio de tramo con jefe de hogar: la fila "Over $103,350 but not over $197,300" usa 24 % menos USD 8.892 (la anterior, 22 % menos 6.825).
return [
    'codigo' => 'tax-hoh-tramo-24',
    'anio' => 2025,
    'descripcion' => 'Jefe de hogar con ingreso gravable de USD 105.000: ya dentro del tramo de 24 % de la hoja (24 % menos USD 8.892).',
    'entradas' => [
        'estado_civil' => 'jefe_de_hogar',
        'ingreso_gravable' => '105000',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 105.000.',
            'valor' => '105000',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 de USD 100.000 o más el impuesto no sale de la Tax Table sino de la Tax Computation Worksheet.',
            'valor' => '105000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con USD 100.000 o más se usa la Tax Computation Worksheet)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'D-a',
            'texto' => 'Fila "Over $103,350 but not over $197,300" de la sección D: columna (a) = línea 15 = USD 105.000.',
            'valor' => '105000',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'D-c',
            'texto' => 'Columna (c) = (a) por (b) = 105.000 por 0.24 = USD 25200.',
            'valor' => '25200',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'D-d',
            'texto' => 'Columna (d), cifra a restar de esa fila: USD 8892.',
            'valor' => '8892',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Tax = (c) menos (d) = 25.200 menos 8.892 = USD 16.308. Es el impuesto de la línea 16.',
            'valor' => '16308',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '16308'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
