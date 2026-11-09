<?php

// Ejemplo de las instrucciones del Form 2441, línea 16. Transcrito a mano.
return [
    'codigo' => 'i2441-ejemplo-efectivo-2000-gastos-900',
    'anio' => 2025,
    'descripcion' => 'Ejemplo del 2441: USD 2.000 en efectivo del plan de cuidado dependiente del empleador (casilla 10 del W-2) y solo USD 900 de gastos calificados.',
    'entradas' => [
        'w2_casilla_10' => '2000',
        'gastos_calificados_incurridos' => '900',
    ],
    'pasos' => [
        [
            'formulario' => '2441',
            'linea' => '12',
            'texto' => 'Beneficios recibidos (casilla 10 del W-2): USD 2.000.',
            'valor' => '2000',
            'fuente' => ['doc' => 'i2441', 'pagina' => 'p. 5 (Line 16, Example)', 'url' => 'https://www.irs.gov/pub/irs-prior/i2441--2025.pdf'],
        ],
        [
            'formulario' => '2441',
            'linea' => '16',
            'texto' => 'Gastos calificados incurridos en 2025 para el niño de 5 años: USD 900.',
            'valor' => '900',
            'fuente' => ['doc' => 'i2441', 'pagina' => 'p. 5 (Line 16, Example)', 'url' => 'https://www.irs.gov/pub/irs-prior/i2441--2025.pdf'],
        ],
    ],
    'lineas' => [
        '2441' => ['12' => '2000', '16' => '900'],
    ],
    'cubre' => ['2441:12', '2441:16'],
    'contraste' => null,
];
