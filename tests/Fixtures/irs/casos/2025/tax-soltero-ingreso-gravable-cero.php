<?php

// Ingreso gravable cero: la primera fila de la Tax Table (0 a menos de 5) da cero impuesto.
return [
    'codigo' => 'tax-soltero-ingreso-gravable-cero',
    'anio' => 2025,
    'descripcion' => 'Soltero con ingreso gravable de USD 0: la primera fila de la Tax Table da USD 0 de impuesto.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'ingreso_gravable' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 0.',
            'valor' => '0',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'tax-table',
            'linea' => 'fila',
            'texto' => 'USD 0 cae en la fila "At least 0, but less than 5".',
            'valor' => '0',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2 (Tax Table, primera fila)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Columna "Single" de esa fila: USD 0. Es el impuesto de la línea 16.',
            'valor' => '0',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2 (Tax Table, columna Single)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '0'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
