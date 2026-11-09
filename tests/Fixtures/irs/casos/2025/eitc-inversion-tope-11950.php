<?php

// Tope de ingreso de inversión: con exactamente USD 11.950 NO se supera (la pregunta dice "more than $11,950") y el cálculo sigue. Es el caso de 1 hijo con salarios de 20.000 e intereses de 11.950.
// El AGI (31.950) supera el ingreso del trabajo (20.000), así que vale el menor de los dos créditos.
return [
    'codigo' => 'eitc-inversion-tope-11950',
    'anio' => 2025,
    'descripcion' => 'Soltero con un hijo de 6 años con SSN válido, salarios de USD 20.000 e intereses de USD 11.950: justo en el tope de ingreso de inversión, todavía puede tomar el crédito (USD 2.950).',
    'entradas' => [
        'estado_civil' => 'soltero',
        'hijos_con_ssn_valido' => 1,
        'edad_del_hijo' => 6,
        'salarios_w2' => '20000',
        'interes_gravable' => '11950',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 20.000. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '20000',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '2b',
            'texto' => 'Intereses gravables: USD 11.950. La línea 11b (AGI) = 20.000 + 11.950 = USD 31.950.',
            'valor' => '11950',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 2b)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-2',
            'texto' => 'Paso 2: ingreso de inversión = 11.950. La pregunta es si es MÁS de USD 11.950: no. Se sigue.',
            'valor' => '11950',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 2, Investment Income, preguntas 1 y 2)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1: con un hijo con SSN válido la línea 11b (31.950) es menor de USD 50.434. Pasa.',
            'valor' => '50434',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 1, All Filers, pregunta 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-1',
            'texto' => 'Hoja A, línea 1: ingreso del trabajo = USD 20.000.',
            'valor' => '20000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-2',
            'texto' => 'Hoja A, línea 2: fila "20,000 a menos de 20,050", columna Single con 1 hijo: USD 4.328.',
            'valor' => '4328',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 19 (EIC Table, fila 20.000 a 20.050)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-3',
            'texto' => 'Hoja A, línea 3: línea 11b del 1040 = USD 31.950. No es igual a la línea 1: se va a la línea 5.',
            'valor' => '31950',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, líneas 3 y 4)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-5',
            'texto' => 'Hoja A, línea 5: la línea 3 (31.950) no es menor de USD 23.350, se busca 31.950 en la tabla.',
            'valor' => '31950',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 5)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-5-tabla',
            'texto' => 'Fila "31,950 a menos de 32,000", columna Single con 1 hijo: USD 2.950.',
            'valor' => '2950',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 21 (EIC Table, fila 31.950 a 32.000)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-6',
            'texto' => 'Hoja A, línea 6: la menor entre la línea 5 (2.950) y la línea 2 (4.328) = USD 2.950. Va en la línea 27a del 1040.',
            'valor' => '2950',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 6)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '27a',
            'texto' => 'Crédito por ingreso del trabajo (línea 6 de la hoja A): USD 2.950.',
            'valor' => '2950',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 27a)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['27a' => '2950'],
    ],
    'cubre' => ['1040:27a'],
    'contraste' => null,
];
