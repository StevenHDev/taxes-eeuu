<?php

// Borde de la franja de USD 5: con USD 5 exactos ya no se está en la fila 0 a menos de 5 sino en la 5 a menos de 15.
return [
    'codigo' => 'tax-mfj-franja-borde-5',
    'anio' => 2025,
    'descripcion' => 'Casado en conjunto con ingreso gravable de USD 5: borde entre la primera franja (0 a 5) y la segunda (5 a menos de 15), que da USD 1.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'ingreso_gravable' => '5',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 5.',
            'valor' => '5',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 menor de USD 100.000 se usa la Tax Table.',
            'valor' => '5',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con menos de USD 100.000 se usa la Tax Table)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'tax-table',
            'linea' => 'fila',
            'texto' => 'USD 5 cae en la fila "At least 5, but less than 15".',
            'valor' => '5',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2 (Tax Table, fila 5 a 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Columna "Married filing jointly" de esa fila: USD 1. Es el impuesto de la línea 16.',
            'valor' => '1',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2 (Tax Table, fila 5 a 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '1'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
