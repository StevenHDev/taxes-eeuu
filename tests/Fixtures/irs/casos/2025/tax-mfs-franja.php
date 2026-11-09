<?php

// Casado por separado en la Tax Table: la columna MFS no es la de MFJ aunque en esta fila coincide con la de soltero (se lee la columna "Married filing separately").
return [
    'codigo' => 'tax-mfs-franja',
    'anio' => 2025,
    'descripcion' => 'Casado que declara por separado con ingreso gravable de USD 12.950: la columna MFS da USD 1.319 (la de MFJ da USD 1.298).',
    'entradas' => [
        'estado_civil' => 'casado_separado',
        'ingreso_gravable' => '12950',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 12.950.',
            'valor' => '12950',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 menor de USD 100.000 se usa la Tax Table.',
            'valor' => '12950',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con menos de USD 100.000 se usa la Tax Table)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'tax-table',
            'linea' => 'fila',
            'texto' => 'USD 12.950 cae en la fila "At least 12,950, but less than 13,000".',
            'valor' => '12950',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 4 (Tax Table, fila 12.950 a 13.000)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Columna "Married filing separately" de esa fila: USD 1.319. Es el impuesto de la línea 16.',
            'valor' => '1319',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 4 (Tax Table, columna Married filing separately)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '1319'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
