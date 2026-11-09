<?php

// Casados en conjunto con dos hijos: la fila 64.400 a 64.450 trae asterisco. La nota de la p. 26 dice: de 64.400 a menos de 64.430 el crédito es USD 3; con 64.430 o más no puede tomarse.
return [
    'codigo' => 'eitc-dos-hijos-mfj-limite',
    'anio' => 2025,
    'descripcion' => 'Matrimonio que declara en conjunto, con dos hijos con SSN válido y salarios de USD 64.429: un dólar antes del tope, el crédito es de apenas USD 3.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'hijos_con_ssn_valido' => 2,
        'edades_de_los_hijos' => '4 y 9',
        'salarios_w2' => '64429',
        'interes_y_dividendos' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 64.429. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '64429',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1: la línea 11b (64.429) es menor de USD 64.430 (declaración conjunta, dos hijos). Pasa.',
            'valor' => '64430',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 1, All Filers, pregunta 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-1',
            'texto' => 'Hoja A, línea 1: ingreso del trabajo = USD 64.429.',
            'valor' => '64429',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, línea 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-2',
            'texto' => 'Hoja A, línea 2: fila "64,400 a menos de 64,450", columna Married filing jointly con 2 hijos: asterisco. Nota: de 64.400 a menos de 64.430 el crédito es USD 3.',
            'valor' => '3',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 26 (EIC Table, fila 64.400 a 64.450 y nota con asterisco)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'A-3',
            'texto' => 'Hoja A, línea 3: línea 11b = USD 64.429, igual a la línea 1: la línea 6 es la de la línea 2.',
            'valor' => '64429',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 46 (Worksheet A, líneas 3 a 6)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '27a',
            'texto' => 'Crédito por ingreso del trabajo (línea 6 de la hoja A): USD 3.',
            'valor' => '3',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 27a)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['27a' => '3'],
    ],
    'cubre' => ['1040:27a'],
    'contraste' => null,
];
