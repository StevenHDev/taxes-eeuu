<?php

// Sin hijos, el crédito exige que la línea 11b sea MENOR de USD 19.104 (Step 1). Con 19.104 exactos el paso 1 falla: "You can't take the credit".
// Suposición anotada: la línea 27a queda en blanco (no se reclama el crédito); aquí se escribe "0" y por eso el caso no declara cobertura.
return [
    'codigo' => 'eitc-sin-hijos-sobre-limite-19104',
    'anio' => 2025,
    'descripcion' => 'Soltero de 30 años, sin hijos, con salarios de USD 19.104: justo en el tope de ingreso, ya no puede tomar el crédito.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'edad_contribuyente' => 30,
        'hijos_con_ssn_valido' => 0,
        'salarios_w2' => '19104',
        'interes_y_dividendos' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '1z',
            'texto' => 'Salarios de los W-2: USD 19.104. Es toda la renta del contribuyente y la línea 11b (ingreso bruto ajustado) queda igual.',
            'valor' => '19104',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 1z)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-1',
            'texto' => 'Paso 1, pregunta 1: sin hijos con SSN válido la línea 11b debe ser MENOR de USD 19.104; con 19.104 no lo es: no puede tomar el crédito.',
            'valor' => '19104',
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
