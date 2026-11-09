<?php

// Borde de USD 100.000, lado de arriba: con USD 100.001 se usa la hoja, sección A; el producto (c) tiene centavos.
// PENDIENTE DE REVISIÓN (dos lecturas): la hoja no dice qué hacer con los centavos de la columna (c). Las instrucciones del 1040 (p. 23, "Rounding Off to Whole Dollars")
// permiten redondear todo a dólares enteros, pero es opcional: si se redondea, la línea 16 es 16.914; si no, 16.914,22. Mientras no se decida, el caso no declara cobertura.
// En "lineas" va la lectura sin redondear (16914.22); la lectura redondeada está en el último paso con la línea "16-redondeada".
return [
    'codigo' => 'tax-soltero-ingreso-gravable-100001-borde',
    'anio' => 2025,
    'descripcion' => 'Soltero con ingreso gravable de USD 100.001: la hoja da USD 16.914,22 sin redondear o USD 16.914 redondeando a dólares enteros (pendiente de revisión).',
    'entradas' => [
        'estado_civil' => 'soltero',
        'ingreso_gravable' => '100001',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 100.001.',
            'valor' => '100001',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 de USD 100.000 o más el impuesto no sale de la Tax Table sino de la Tax Computation Worksheet.',
            'valor' => '100001',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con USD 100.000 o más se usa la Tax Computation Worksheet)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'A-a',
            'texto' => 'Fila "At least $100,000 but not over $103,350" de la sección A: columna (a) = línea 15 = USD 100.001.',
            'valor' => '100001',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección A, soltero)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-impuesto',
            'linea' => 'A-c',
            'texto' => 'Columna (c) = (a) por (b) = 100.001 por 0.22 = USD 22000.22.',
            'valor' => '22000.22',
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
            'texto' => 'Lectura A, sin redondear: (c) menos (d) = 22.000,22 menos 5.086,00 = USD 16.914,22. PENDIENTE DE REVISIÓN.',
            'valor' => '16914.22',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 14 (Tax Computation Worksheet, Sección A, soltero)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-redondeada',
            'texto' => 'Lectura B, redondeando todo a dólares enteros (menos de 50 centavos se descartan): USD 16.914. PENDIENTE DE REVISIÓN.',
            'valor' => '16914',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 23 (Rounding Off to Whole Dollars)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '16914.22'],
    ],
    'cubre' => [],
    'contraste' => null,
];
