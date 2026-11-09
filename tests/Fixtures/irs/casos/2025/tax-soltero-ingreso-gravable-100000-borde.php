<?php

// Borde de USD 100.000 de la línea 16 del Form 1040 (Tax Table vs. Tax Computation Worksheet). Escrito a mano desde el PDF, sin mirar ninguna calculadora.
// Con ingreso gravable de USD 100.000 exactos ya no se usa la Tax Table (su última fila es 99.950 a menos de 100.000): se usa la hoja, sección A.
// Los vecinos del borde están en tax-soltero-ingreso-gravable-99999-borde y tax-soltero-ingreso-gravable-100001-borde.
return [
    'codigo' => 'tax-soltero-ingreso-gravable-100000-borde',
    'anio' => 2025,
    'descripcion' => 'Soltero con ingreso gravable de USD 100.000 exactos: primer valor que ya no sale de la Tax Table sino de la Tax Computation Worksheet (22 % menos USD 5.086).',
    'entradas' => [
        'estado_civil' => 'soltero',
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
            'linea' => 'A-a',
            'texto' => 'Fila "At least $100,000 but not over $103,350" de la sección A: columna (a) = línea 15 = USD 100.000.',
            'valor' => '100000',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección A, soltero)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'A-c',
            'texto' => 'Columna (c) = (a) por (b) = 100.000 por 0.22 = USD 22000.',
            'valor' => '22000',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección A, soltero)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'A-d',
            'texto' => 'Columna (d), cifra a restar de esa fila: USD 5086.',
            'valor' => '5086',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección A, soltero)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Tax = (c) menos (d) = 22.000 menos 5.086 = USD 16.914. Es el impuesto de la línea 16.',
            'valor' => '16914',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección A, soltero)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '16914'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
