<?php

// Hoja de trabajo A de la línea 27a (instrucciones del 1040, p. 46) para un soltero sin hijos con salarios de USD 10.600; la fila 10.600 a 10.650 todavía da el máximo (la siguiente, 10.650 a 10.700, ya baja a 645).
return [
    'codigo' => 'eitc-sin-hijos',
    'anio' => 2025,
    'descripcion' => 'Soltero de 30 años, sin hijos, con salarios de USD 10.600: el crédito sigue en su máximo de USD 649 justo antes de que la tabla empiece a bajar.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'edad_contribuyente' => 30,
        'hijos_con_ssn_valido' => 0,
        'salarios_w2' => '10600',
        'interes_y_dividendos' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 10.600. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '10600',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1: sin hijos con SSN válido, la línea 11b (10.600) es menor de USD 19.104. Pasa.',
            'valor' => '19104',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 1, All Filers, pregunta 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-4',
            'texto' => 'Paso 4: sin hijo calificado, el contribuyente debe tener al menos 25 y menos de 65 años a fin de 2025. Tiene 30. Pasa.',
            'valor' => '30',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 41 (Step 4, Filers Without a Qualifying Child)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-1',
            'texto' => 'Hoja A, línea 1: ingreso del trabajo = USD 10.600.',
            'valor' => '10600',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-2',
            'texto' => 'Hoja A, línea 2: fila "10,600 a menos de 10,650", columna "Single, head of household, or qualifying surviving spouse" con 0 hijos: USD 649.',
            'valor' => '649',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 17 (EIC Table, fila 10.600 a 10.650)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-3',
            'texto' => 'Hoja A, línea 3: línea 11b del 1040 = USD 10.600. Es igual a la línea 1.',
            'valor' => '10600',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 3)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-6',
            'texto' => 'Hoja A, líneas 4 y 6: como las líneas 3 y 1 son iguales se salta la línea 5 y la línea 6 es la de la línea 2: USD 649. Va en la línea 27a del 1040.',
            'valor' => '649',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, líneas 4 a 6)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '27a',
            'texto' => 'Crédito por ingreso del trabajo (línea 6 de la hoja A): USD 649.',
            'valor' => '649',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 27a)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['27a' => '649'],
    ],
    'cubre' => ['1040:27a'],
    'contraste' => null,
];
