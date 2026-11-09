<?php

// Soltero con tres hijos y USD 61.555 de salarios: el paso 1 exige MENOS de USD 61.555 y falla. Suposición: línea 27a en blanco, aquí "0"; por eso no declara cobertura.
return [
    'codigo' => 'eitc-tres-hijos-sobre-limite',
    'anio' => 2025,
    'descripcion' => 'Soltero con tres hijos con SSN válido y salarios de USD 61.555: justo en el tope, ya no puede tomar el crédito.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'hijos_con_ssn_valido' => 3,
        'edades_de_los_hijos' => '3, 7 y 12',
        'salarios_w2' => '61555',
        'interes_y_dividendos' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 61.555. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '61555',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1, pregunta 1: con tres o más hijos con SSN válido la línea 11b debe ser MENOR de USD 61.555; con 61.555 no lo es: no puede tomar el crédito.',
            'valor' => '61555',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 1, All Filers, pregunta 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => '1040',
            'linea' => '27a',
            'texto' => 'Sin crédito: la línea 27a queda en blanco (se anota 0).',
            'valor' => '0',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 2 (línea 27a)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        '1040' => ['27a' => '0'],
    ],
    'cubre' => [],
    'contraste' => null,
];
