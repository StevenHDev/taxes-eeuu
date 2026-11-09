<?php

// Ejemplo de las instrucciones del Schedule 1-A (Instructions for Form 1040, p. 107). Transcrito a mano.
// El ejemplo no nombra la línea del formulario: la 14c (suma de 14a y 14b) se asume y por eso NO declara cobertura.
return [
    'codigo' => 'i1040gi-s1a-horas-extra-ejemplo-1',
    'anio' => 2025,
    'descripcion' => 'Ejemplo 1 de horas extra calificadas: empleado FLSA con USD 50.000 de pago regular y USD 15.000 por horas extra a tiempo y medio.',
    'entradas' => [
        'pago_regular' => '50000',
        'pago_por_horas_extra' => '15000',
        'recargo_horas_extra' => 'tiempo y medio',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-1a',
            'linea' => '14c',
            'texto' => 'Solo cuenta la "mitad" del tiempo y medio: 15.000 dividido por 3 = USD 5.000 (la línea es una suposición: el ejemplo solo dice "para calcular la deducción por horas extra calificadas").',
            'valor' => '5000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 107 (Schedule 1-A, Qualified overtime compensation, Example 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
    ],
    'lineas' => [
        'sch-1a' => ['14c' => '5000'],
    ],
    'cubre' => [],
    'contraste' => null,
];
