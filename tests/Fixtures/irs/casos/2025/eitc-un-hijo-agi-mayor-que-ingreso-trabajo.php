<?php

// Un hijo, soltero, con USD 500 de intereses gravables: el AGI (30.500) es mayor que el ingreso del trabajo (30.000). La hoja A da el MENOR de los dos créditos (líneas 5 y 2).
// Los intereses (USD 500) son menos de USD 11.950, así que no se activa el límite de ingreso de inversión (Step 2).
return [
    'codigo' => 'eitc-un-hijo-agi-mayor-que-ingreso-trabajo',
    'anio' => 2025,
    'descripcion' => 'Soltero con un hijo de 6 años con SSN válido, salarios de USD 30.000 e intereses de USD 500: el crédito se toma sobre el AGI de USD 30.500 y es menor que el calculado sobre el ingreso del trabajo.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'hijos_con_ssn_valido' => 1,
        'edad_del_hijo' => 6,
        'salarios_w2' => '30000',
        'interes_gravable' => '500',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 30.000. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '30000',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '2b',
            'texto' => 'Intereses gravables: USD 500. La línea 11b (AGI) = 30.000 + 500 = USD 30.500.',
            'valor' => '500',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 2b)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-2',
            'texto' => 'Paso 2: ingreso de inversión = 500, no es más de USD 11.950. Pasa.',
            'valor' => '11950',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 2, Investment Income)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1: la línea 11b (30.500) es menor de USD 50.434 con un hijo. Pasa.',
            'valor' => '50434',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 1, All Filers, pregunta 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-1',
            'texto' => 'Hoja A, línea 1: ingreso del trabajo = USD 30.000 (los intereses no cuentan).',
            'valor' => '30000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-2',
            'texto' => 'Hoja A, línea 2: fila "30,000 a menos de 30,050", columna Single con 1 hijo: USD 3.261.',
            'valor' => '3261',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 20 (EIC Table, fila 30.000 a 30.050)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-3',
            'texto' => 'Hoja A, línea 3: línea 11b del 1040 = USD 30.500. No es igual a la línea 1: se va a la línea 5.',
            'valor' => '30500',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, líneas 3 y 4)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-5',
            'texto' => 'Hoja A, línea 5: con un hijo, la línea 3 (30.500) no es menor de USD 23.350, así que se busca 30.500 en la tabla.',
            'valor' => '30500',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 5)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-5-tabla',
            'texto' => 'Fila "30,500 a menos de 30,550", columna Single con 1 hijo: USD 3.181.',
            'valor' => '3181',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 20 (EIC Table, fila 30.500 a 30.550)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-6',
            'texto' => 'Hoja A, línea 6: la menor entre la línea 5 (3.181) y la línea 2 (3.261) = USD 3.181. Va en la línea 27a del 1040.',
            'valor' => '3181',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 6)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '27a',
            'texto' => 'Crédito por ingreso del trabajo (línea 6 de la hoja A): USD 3.181.',
            'valor' => '3181',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 27a)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['27a' => '3181'],
    ],
    'cubre' => ['1040:27a'],
    'contraste' => null,
];
