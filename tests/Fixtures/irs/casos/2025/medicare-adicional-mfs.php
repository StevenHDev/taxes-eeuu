<?php

// Casado que declara por separado: el umbral de la línea 5 es USD 125.000 (en vez de 200.000 o 250.000).
return [
    'codigo' => 'medicare-adicional-mfs',
    'anio' => 2025,
    'descripcion' => 'Casado que declara por separado con salarios de Medicare de USD 150.000: el umbral es USD 125.000 y el Form 8959 da USD 225 de impuesto adicional (línea 11 del Schedule 2).',
    'entradas' => [
        'estado_civil' => 'casado_separado',
        'salarios_medicare_w2_casilla_5' => '150000',
        'ingreso_autoempleo' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'f8959',
            'linea' => '1',
            'texto' => 'Salarios y propinas de Medicare del W-2, casilla 5: USD 150.000.',
            'valor' => '150000',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '4',
            'texto' => 'Suma de las líneas 1 a 3: USD 150.000.',
            'valor' => '150000',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '5',
            'texto' => 'Umbral para casado que declara por separado: USD 125.000.',
            'valor' => '125000',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '6',
            'texto' => 'Línea 4 menos línea 5 = 150.000 menos 125.000 = USD 25.000.',
            'valor' => '25000',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '7',
            'texto' => 'Línea 6 por 0,9 % (0.009) = USD 225.',
            'valor' => '225',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '18',
            'texto' => 'Suma de las líneas 7, 13 y 17 (13 y 17 en blanco): USD 225. Se anota también en la línea 11 del Schedule 2.',
            'valor' => '225',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part IV, línea 18)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'sch-2',
            'linea' => '11',
            'texto' => 'Impuesto adicional de Medicare, línea 11 del Schedule 2: USD 225.',
            'valor' => '225',
            'fuente' => ['doc' => 'f1040s2', 'pagina' => 'p. 1 (línea 11, Additional Medicare Tax)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040s2--2025.pdf'],
        ],
    ],
    'lineas' => [
        'sch-2' => ['11' => '225'],
    ],
    'cubre' => ['sch-2:11'],
    'contraste' => null,
];
