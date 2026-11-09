<?php

// Ejemplo de las instrucciones del Schedule 1-A (Instructions for Form 1040, p. 107). Transcrito a mano.
// Igual que el ejemplo 1: la línea 14c es una suposición y no declara cobertura.
return [
    'codigo' => 'i1040gi-s1a-horas-extra-ejemplo-2',
    'anio' => 2025,
    'descripcion' => 'Ejemplo 2 de horas extra calificadas: pago de horas extra al doble; USD 50.000 en horas normales y USD 20.000 en horas extra, con la colilla separando USD 10.000 de tarifa normal y USD 10.000 de recargo.',
    'entradas' => [
        'pago_horas_no_extra' => '50000',
        'pago_por_horas_extra' => '20000',
        'horas_extra_a_tarifa_normal' => '10000',
        'recargo_pagado' => '10000',
    ],
    'pasos' => [
        [
            'formulario' => '1-A',
            'linea' => '14c',
            'texto' => 'El tiempo y medio de USD 10.000 de tarifa normal son USD 15.000; la "mitad" exigida por la FLSA es 15.000 / 3 = USD 5.000, que es lo que se incluye (no los 10.000 de recargo pagados).',
            'valor' => '5000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 107 (Schedule 1-A, Qualified overtime compensation, Example 2)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1-A' => ['14c' => '5000'],
    ],
    'cubre' => [],
    'contraste' => null,
];
