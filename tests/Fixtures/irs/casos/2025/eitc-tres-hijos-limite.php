<?php

// Soltero con tres hijos: la fila 61.550 a 61.600 trae asterisco triple. Nota de la p. 25: de 61.550 a menos de 61.555 el crédito es USD 1; con 61.555 o más no puede tomarse (la fila anterior, 61.500 a 61.550, da 6).
return [
    'codigo' => 'eitc-tres-hijos-limite',
    'anio' => 2025,
    'descripcion' => 'Soltero con tres hijos con SSN válido y salarios de USD 61.554: un dólar antes del tope, el crédito es de apenas USD 1.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'hijos_con_ssn_valido' => 3,
        'edades_de_los_hijos' => '3, 7 y 12',
        'salarios_w2' => '61554',
        'interes_y_dividendos' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 61.554. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '61554',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1: con tres o más hijos con SSN válido la línea 11b (61.554) debe ser menor de USD 61.555. Pasa.',
            'valor' => '61555',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 1, All Filers, pregunta 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-1',
            'texto' => 'Hoja A, línea 1: ingreso del trabajo = USD 61.554.',
            'valor' => '61554',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-2',
            'texto' => 'Hoja A, línea 2: fila "61,550 a menos de 61,600", columna Single con 3 hijos: asterisco triple. Nota: de 61.550 a menos de 61.555 el crédito es USD 1.',
            'valor' => '1',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 25 (EIC Table, fila 61.550 a 61.600 y nota ***)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-3',
            'texto' => 'Hoja A, línea 3: línea 11b = USD 61.554, igual a la línea 1: la línea 6 es la de la línea 2.',
            'valor' => '61554',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, líneas 3 a 6)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '27a',
            'texto' => 'Crédito por ingreso del trabajo (línea 6 de la hoja A): USD 1.',
            'valor' => '1',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 27a)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['27a' => '1'],
    ],
    'cubre' => ['1040:27a'],
    'contraste' => null,
];
