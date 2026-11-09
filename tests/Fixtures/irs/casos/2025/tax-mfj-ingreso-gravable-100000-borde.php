<?php

// Borde de USD 100.000 para casados en conjunto: hoja, sección B. La última fila de la Tax Table (99.950 a 100.000) da 11.823 en MFJ (p. 13); con 100.000 exactos se pasa a la hoja.
return [
    'codigo' => 'tax-mfj-ingreso-gravable-100000-borde',
    'anio' => 2025,
    'descripcion' => 'Casado en conjunto con ingreso gravable de USD 100.000: Tax Computation Worksheet, sección B (22 % menos USD 10.172).',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'ingreso_gravable' => '100000',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 100.000.',
            'valor' => '100000',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 de USD 100.000 o más el impuesto no sale de la Tax Table sino de la Tax Computation Worksheet.',
            'valor' => '100000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con USD 100.000 o más se usa la Tax Computation Worksheet)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'B-a',
            'texto' => 'Fila "At least $100,000 but not over $206,700" de la sección B: columna (a) = línea 15 = USD 100.000.',
            'valor' => '100000',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección B, casado conjunto o QSS)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'B-c',
            'texto' => 'Columna (c) = (a) por (b) = 100.000 por 0.22 = USD 22000.',
            'valor' => '22000',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección B, casado conjunto o QSS)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'B-d',
            'texto' => 'Columna (d), cifra a restar de esa fila: USD 10172.',
            'valor' => '10172',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección B, casado conjunto o QSS)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Tax = (c) menos (d) = 22.000 menos 10.172 = USD 11.828. Es el impuesto de la línea 16.',
            'valor' => '11828',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección B, casado conjunto o QSS)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '11828'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
