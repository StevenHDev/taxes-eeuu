<?php

// Ejemplo de las instrucciones del Form 2441, línea 14. Transcrito a mano.
return [
    'codigo' => 'i2441-ejemplo-caja-10-reembolso-parcial',
    'anio' => 2025,
    'descripcion' => 'Ejemplo del 2441: el empleador apartó USD 5.000 en su plan de cuidado dependiente (casilla 10 del W-2) y se gastaron y reembolsaron USD 4.950.',
    'entradas' => [
        'w2_casilla_10' => '5000',
        'gastos_calificados_reembolsados' => '4950',
    ],
    'pasos' => [
        [
            'formulario' => 'f2441',
            'linea' => '12',
            'texto' => 'Beneficios de cuidado dependiente recibidos en 2025 (casilla 10 del W-2): USD 5.000.',
            'valor' => '5000',
            'fuente' => ['doc' => 'i2441', 'pagina' => 'p. 5 (Line 14, Example)', 'url' => 'https://www.irs.gov/pub/irs-prior/i2441--2025.pdf'],
        ],
        [
            'formulario' => 'f2441',
            'linea' => '14',
            'texto' => 'Monto perdido (no reembolsado): 5.000 menos 4.950 = USD 50.',
            'valor' => '50',
            'fuente' => ['doc' => 'i2441', 'pagina' => 'p. 5 (Line 14, Example)', 'url' => 'https://www.irs.gov/pub/irs-prior/i2441--2025.pdf'],
        ],
    ],
    'lineas' => [
        'f2441' => ['12' => '5000', '14' => '50'],
    ],
    'cubre' => ['f2441:12', 'f2441:14'],
    'contraste' => null,
];
