<?php

// Un hijo, soltero. La fila 23.300 a 23.350 da el máximo (4.328, p. 19); la siguiente, 23.350 a 23.400, ya baja a 4.324. También es el monto de la línea 5 de la hoja A ("menos de USD 23.350").
// Con salarios únicamente, la línea 3 (AGI) y la línea 1 son iguales y no se llega a la línea 5.
return [
    'codigo' => 'eitc-un-hijo-borde-reduccion',
    'anio' => 2025,
    'descripcion' => 'Soltero con un hijo de 6 años con SSN válido y salarios de USD 23.350: primera fila donde el crédito empieza a bajar (USD 4.324 en vez de 4.328).',
    'entradas' => [
        'estado_civil' => 'soltero',
        'hijos_con_ssn_valido' => 1,
        'edad_del_hijo' => 6,
        'salarios_w2' => '23350',
        'interes_y_dividendos' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 23.350. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '23350',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1: con un hijo con SSN válido la línea 11b (23.350) es menor de USD 50.434. Pasa.',
            'valor' => '50434',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 1, All Filers, pregunta 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-1',
            'texto' => 'Hoja A, línea 1: ingreso del trabajo = USD 23.350.',
            'valor' => '23350',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-2',
            'texto' => 'Hoja A, línea 2: fila "23,350 a menos de 23,400", columna "Single, head of household, or qualifying surviving spouse" con 1 hijo: USD 4.324 (la fila anterior, 23.300 a 23.350, da 4.328).',
            'valor' => '4324',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 19 (EIC Table, fila 23.350 a 23.400)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-3',
            'texto' => 'Hoja A, línea 3: línea 11b del 1040 = USD 23.350. Igual a la línea 1, se salta la línea 5.',
            'valor' => '23350',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 3)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-6',
            'texto' => 'Hoja A, línea 6 = línea 2 = USD 4.324. Va en la línea 27a del 1040.',
            'valor' => '4324',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, líneas 4 a 6)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '27a',
            'texto' => 'Crédito por ingreso del trabajo (línea 6 de la hoja A): USD 4.324.',
            'valor' => '4324',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 27a)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['27a' => '4324'],
    ],
    'cubre' => ['1040:27a'],
    'contraste' => null,
];
