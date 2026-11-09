<?php

// Casados que declaran en conjunto con dos hijos con SSN válido y salarios entre los dos de USD 40.000. En la misma fila la columna MFJ (5.140) es distinta de la de soltero/jefe de hogar (3.640).
return [
    'codigo' => 'eitc-dos-hijos-mfj',
    'anio' => 2025,
    'descripcion' => 'Matrimonio que declara en conjunto, con dos hijos (4 y 9 años) con SSN válido y salarios totales de USD 40.000: el crédito de la columna MFJ con 2 hijos es USD 5.140.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'hijos_con_ssn_valido' => 2,
        'edades_de_los_hijos' => '4 y 9',
        'salarios_w2' => '40000',
        'interes_y_dividendos' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 40.000. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '40000',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1: con dos hijos con SSN válido y declaración conjunta, la línea 11b (40.000) es menor de USD 64.430. Pasa.',
            'valor' => '64430',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 1, All Filers, pregunta 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-1',
            'texto' => 'Hoja A, línea 1: ingreso del trabajo = USD 40.000.',
            'valor' => '40000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-2',
            'texto' => 'Hoja A, línea 2: fila "40,000 a menos de 40,050", columna "Married filing jointly" con 2 hijos: USD 5.140 (la columna Single con 2 hijos da 3.640).',
            'valor' => '5140',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 22 (EIC Table, fila 40.000 a 40.050)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-3',
            'texto' => 'Hoja A, línea 3: línea 11b = USD 40.000, igual a la línea 1: la línea 6 es la de la línea 2.',
            'valor' => '40000',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, líneas 3 a 6)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '27a',
            'texto' => 'Crédito por ingreso del trabajo (línea 6 de la hoja A): USD 5.140.',
            'valor' => '5140',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 27a)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['27a' => '5140'],
    ],
    'cubre' => ['1040:27a'],
    'contraste' => null,
];
