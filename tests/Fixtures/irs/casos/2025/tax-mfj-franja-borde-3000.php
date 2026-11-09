<?php

// Borde de la franja de USD 50: hasta 3.000 las franjas son de 25 dólares; desde 3.000 son de 50. La fila anterior (2.975 a 3.000, p. 2) da 299 en MFJ.
return [
    'codigo' => 'tax-mfj-franja-borde-3000',
    'anio' => 2025,
    'descripcion' => 'Casado en conjunto con ingreso gravable de USD 3.000: primera fila de las franjas de USD 50 (la fila 2.975 a 3.000 da USD 299; la 3.000 a 3.050 da USD 303).',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'ingreso_gravable' => '3000',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 3.000.',
            'valor' => '3000',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 15)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16-metodo',
            'texto' => 'Con una línea 15 menor de USD 100.000 se usa la Tax Table.',
            'valor' => '3000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 36 (línea 16: con menos de USD 100.000 se usa la Tax Table)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'tax-table',
            'linea' => 'fila',
            'texto' => 'USD 3.000 cae en la fila "At least 3,000, but less than 3,050".',
            'valor' => '3000',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 3 (Tax Table, fila 3.000 a 3.050)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Columna "Married filing jointly" de esa fila: USD 303. Es el impuesto de la línea 16.',
            'valor' => '303',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 3 (Tax Table, fila 3.000 a 3.050)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '303'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
