<?php

// 6.9b. Deducción estándar de alguien que sus padres pueden reclamar como dependiente (Standard Deduction Worksheet for Dependents, i1040gi p. 35).
// Estudiante soltero de 19 años con USD 4.000 de salarios W-2 y nada más: la deducción es su ingreso del trabajo más USD 450, no los USD 15.750.
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'deduccion-estandar-dependiente',
    'anio' => 2025,
    'descripcion' => 'Estudiante soltero de 19 años, dependiente de sus padres, con USD 4.000 de salarios: la hoja de dependientes da una deducción estándar de USD 4.450 en la línea 12e.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'fecha_nacimiento_contribuyente' => '2006-03-15',
        'contribuyente_ciego' => 'no',
        'alguien_puede_reclamarlo_como_dependiente' => 'si',
        'w2_casilla_1' => '4000',
        'otros_ingresos' => '0',
        'detalla_deducciones' => 'no',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '12d',
            'texto' => 'Sin casillas de 65 años o ceguera: 0. (Sí se marca la 12a, "Someone can claim you as a dependent".)',
            'valor' => '0',
            'fuente' => $fuente('f1040', 'p. 2 (líneas 12a y 12d)'),
        ],
        [
            'formulario' => 'hoja-deduccion-dependientes',
            'linea' => '1',
            'texto' => 'Casillas marcadas (nacido antes del 2 de enero de 1961 o ciego): 0.',
            'valor' => '0',
            'fuente' => $fuente('i1040gi', 'p. 35 (Standard Deduction Worksheet for Dependents, línea 1)'),
        ],
        [
            'formulario' => 'hoja-deduccion-dependientes',
            'linea' => '2',
            'texto' => '¿Ingreso del trabajo de más de USD 900? Sí (4.000, la línea 1z). Se suman USD 450: 4.000 + 450 = USD 4.450.',
            'valor' => '4450',
            'fuente' => $fuente('i1040gi', 'p. 35 (Standard Deduction Worksheet for Dependents, línea 2 y nota de earned income)'),
        ],
        [
            'formulario' => 'hoja-deduccion-dependientes',
            'linea' => '3',
            'texto' => 'Monto del estado civil (soltero): USD 15.750.',
            'valor' => '15750',
            'fuente' => $fuente('i1040gi', 'p. 35 (Standard Deduction Worksheet for Dependents, línea 3)'),
        ],
        [
            'formulario' => 'hoja-deduccion-dependientes',
            'linea' => '4a',
            'texto' => 'La menor entre la línea 2 (4.450) y la 3 (15.750) = 4.450. Nacido después del 1 de enero de 1961 y no ciego: se para aquí y va a la línea 12e.',
            'valor' => '4450',
            'fuente' => $fuente('i1040gi', 'p. 35 (Standard Deduction Worksheet for Dependents, línea 4a)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '12e',
            'texto' => 'Deducción estándar: USD 4.450.',
            'valor' => '4450',
            'fuente' => $fuente('i1040gi', 'p. 35 (Standard Deduction Worksheet for Dependents, línea 4a)'),
        ],
    ],
    'lineas' => [
        '1040' => ['12d' => '0', '12e' => '4450'],
        'hoja-deduccion-dependientes' => ['1' => '0', '2' => '4450', '3' => '15750', '4a' => '4450'],
    ],
    'cubre' => ['1040:12d', '1040:12e'],
    'contraste' => null,
];
