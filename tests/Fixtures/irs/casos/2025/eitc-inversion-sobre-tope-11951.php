<?php

// Tope de ingreso de inversión: con USD 11.951 SÍ se supera USD 11.950. Sin Form 4797, el Step 2 (pregunta 3) dice STOP: no puede tomar el crédito.
// Suposición: línea 27a en blanco, aquí "0"; por eso no declara cobertura.
return [
    'codigo' => 'eitc-inversion-sobre-tope-11951',
    'anio' => 2025,
    'descripcion' => 'Soltero con un hijo de 6 años con SSN válido, salarios de USD 20.000 e intereses de USD 11.951: un dólar sobre el tope de ingreso de inversión, no puede tomar el crédito.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'hijos_con_ssn_valido' => 1,
        'edad_del_hijo' => 6,
        'salarios_w2' => '20000',
        'interes_gravable' => '11951',
        'presenta_form_4797' => 'no',
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
            'texto' => 'Intereses gravables: USD 11.951.',
            'valor' => '11951',
            'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1 (línea 2b)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040--2025.pdf'],
        ],
        [
            'formulario' => 'hoja-eic',
            'linea' => 'paso-2',
            'texto' => 'Paso 2: ingreso de inversión = 11.951, más de USD 11.950. Pregunta 3: no presenta Form 4797, entonces STOP: no puede tomar el crédito.',
            'valor' => '11951',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 40 (Step 2, Investment Income, preguntas 2 y 3)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
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
