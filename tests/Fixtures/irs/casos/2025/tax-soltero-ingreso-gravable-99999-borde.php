<?php

// Borde de USD 100.000 de la línea 16 del Form 1040, lado de abajo: con USD 99.999 todavía se usa la Tax Table (fila 99.950 a menos de 100.000).
// La columna "Single" de esa fila se leyó directamente en el PDF.
return [
    'codigo' => 'tax-soltero-ingreso-gravable-99999-borde',
    'anio' => 2025,
    'descripcion' => 'Soltero con ingreso gravable de USD 99.999: último valor que sale de la Tax Table (fila 99.950 a 100.000).',
    'entradas' => [
        'estado_civil' => 'soltero',
        'ingreso_gravable' => '99999',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 99.999.',
            'valor' => '99999',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 menor de USD 100.000 se usa la Tax Table.',
            'valor' => '99999',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con menos de USD 100.000 se usa la Tax Table)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'tax-table',
            'linea' => 'fila',
            'texto' => 'USD 99.999 cae en la fila "At least 99,950, but less than 100,000".',
            'valor' => '99950',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 13 (Tax Table, última fila)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Columna "Single" de esa fila: USD 16.909. Es el impuesto de la línea 16.',
            'valor' => '16909',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 13 (Tax Table, columna Single)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '16909'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
