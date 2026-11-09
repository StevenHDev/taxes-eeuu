<?php

// Ejemplo del propio IRS (anclas de la capa 2). Transcrito a mano; no mira ninguna calculadora.
return [
    'codigo' => 'pub1040-tax-table-mfj-25300',
    'anio' => 2025,
    'descripcion' => 'Ejemplo de la Tax Table: pareja casada que declara en conjunto con ingreso gravable de USD 25.300.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'ingreso_gravable' => '25300',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso gravable de la línea 15: USD 25.300. Se busca la franja 25.300 a 25.350 de la Tax Table.',
            'valor' => '25300',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'En la columna "Married filing jointly" de esa franja el IRS lee USD 2.562; es el impuesto que va en la línea 16.',
            'valor' => '2562',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2 (ejemplo "Sample Table")', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['16' => '2562'],
    ],
    'cubre' => ['1040:16'],
    'contraste' => null,
];
