<?php

// Borde de la franja de USD 25: antes de 25 las franjas son de 10 dólares; desde 25 hasta 3.000 son de 25.
return [
    'codigo' => 'tax-mfj-franja-borde-25',
    'anio' => 2025,
    'descripcion' => 'Casado en conjunto con ingreso gravable de USD 25: primer ingreso de las franjas de USD 25 (la fila 15 a 25 da USD 2; la 25 a 50 da USD 4).',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'ingreso_gravable' => '25',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 25.',
            'valor' => '25',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 menor de USD 100.000 se usa la Tax Table.',
            'valor' => '25',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con menos de USD 100.000 se usa la Tax Table)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'tax-table',
            'linea' => 'fila',
            'texto' => 'USD 25 cae en la fila "At least 25, but less than 50".',
            'valor' => '25',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2 (Tax Table, fila 25 a 50)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Columna "Married filing jointly" de esa fila: USD 4. Es el impuesto de la línea 16.',
            'valor' => '4',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2 (Tax Table, fila 25 a 50)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '4'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
