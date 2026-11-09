<?php

// Para activarlo: fijar f8959 (https://www.irs.gov/pub/irs-prior/f8959--2025.pdf, SHA-256 13e64004948331d2b708c80b1b664bf69675028edd0b84f2de6e3ca9afaa996d, 1 página), agregarlo a fuentes.json y renombrar este archivo a .php.
// El código "ssa-1040-..." viene así del plan; no tiene que ver con el Seguro Social (SSA).
return [
    'codigo' => 'medicare-adicional-soltero',
    'anio' => 2025,
    'descripcion' => 'Soltero con salarios de Medicare (casilla 5 del W-2) de USD 230.000: el Form 8959 da USD 270 de impuesto adicional de Medicare, que va en la línea 11 del Schedule 2.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'salarios_medicare_w2_casilla_5' => '230000',
        'ingreso_autoempleo' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'f8959',
            'linea' => '1',
            'texto' => 'Salarios y propinas de Medicare del W-2, casilla 5: USD 230.000.',
            'valor' => '230000',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '4',
            'texto' => 'Suma de las líneas 1 a 3 (sin propinas no reportadas ni Form 8919): USD 230.000.',
            'valor' => '230000',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '5',
            'texto' => 'Umbral para soltero, jefe de hogar o QSS: USD 200.000.',
            'valor' => '200000',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '6',
            'texto' => 'Línea 4 menos línea 5 = 230.000 menos 200.000 = USD 30.000.',
            'valor' => '30000',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '7',
            'texto' => 'Línea 6 por 0,9 % (0.009) = USD 270.',
            'valor' => '270',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part I, líneas 1 a 7)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'f8959',
            'linea' => '18',
            'texto' => 'Suma de las líneas 7, 13 y 17 (13 y 17 en blanco): USD 270. Se anota también en la línea 11 del Schedule 2.',
            'valor' => '270',
            'fuente' => ['doc' => 'f8959', 'pagina' => 'p. 1 (Part IV, línea 18)', 'url' => 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
        ],
        [
            'formulario' => 'sch-2',
            'linea' => '11',
            'texto' => 'Impuesto adicional de Medicare, línea 11 del Schedule 2: USD 270.',
            'valor' => '270',
            'fuente' => ['doc' => 'f1040s2', 'pagina' => 'p. 1 (línea 11, Additional Medicare Tax)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040s2--2025.pdf'],
        ],
    ],
    'lineas' => [
        'sch-2' => ['11' => '270'],
    ],
    'cubre' => ['sch-2:11'],
    'contraste' => null,
];
