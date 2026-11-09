<?php

// Primer dólar del tramo de 24 % para jefe de hogar ("Over $103,350"): 24 % de 103.351 menos 8.892.
// PENDIENTE DE REVISIÓN (dos lecturas): el producto (c) tiene centavos (24.804,24) y la línea 16 queda en 15.912,24 sin redondear o 15.912 redondeando a dólares enteros
// (opcional, instrucciones del 1040, p. 23). El caso no declara cobertura; en "lineas" va la lectura sin redondear.
return [
    'codigo' => 'tax-hoh-tramo-24-borde-103351',
    'anio' => 2025,
    'descripcion' => 'Jefe de hogar con ingreso gravable de USD 103.351: primer dólar del tramo de 24 %; la hoja da USD 15.912,24 sin redondear o USD 15.912 redondeando (pendiente de revisión).',
    'entradas' => [
        'estado_civil' => 'jefe_de_hogar',
        'ingreso_gravable' => '103351',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 103.351.',
            'valor' => '103351',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 de USD 100.000 o más el impuesto no sale de la Tax Table sino de la Tax Computation Worksheet.',
            'valor' => '103351',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con USD 100.000 o más se usa la Tax Computation Worksheet)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'D-a',
            'texto' => 'Fila "Over $103,350 but not over $197,300" de la sección D: columna (a) = línea 15 = USD 103.351.',
            'valor' => '103351',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'D-c',
            'texto' => 'Columna (c) = (a) por (b) = 103.351 por 0.24 = USD 24804.24.',
            'valor' => '24804.24',
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
            'texto' => 'Lectura A, sin redondear: (c) menos (d) = 24.804,24 menos 8.892,00 = USD 15.912,24. PENDIENTE DE REVISIÓN.',
            'valor' => '15912.24',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 15 (Tax Computation Worksheet, Sección D, jefe de hogar)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-redondeada',
            'texto' => 'Lectura B, redondeando todo a dólares enteros: USD 15.912. PENDIENTE DE REVISIÓN.',
            'valor' => '15912',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 23 (Rounding Off to Whole Dollars)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '15912.24'],
    ],
    'cubre' => [],
    'contraste' => null,
];
