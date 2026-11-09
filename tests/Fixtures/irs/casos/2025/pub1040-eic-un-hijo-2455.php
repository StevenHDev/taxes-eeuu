<?php

// Ejemplo del propio IRS (anclas de la capa 2). El ejemplo termina en la entrada de la hoja de la EIC;
// no nombra una línea del 1040, por eso no declara cobertura de líneas.
return [
    'codigo' => 'pub1040-eic-un-hijo-2455',
    'anio' => 2025,
    'descripcion' => 'Ejemplo de la EIC Table: soltero con un hijo con SSN válido y un monto de USD 2.455 en la hoja de la EIC.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'hijos_con_ssn_valido' => 1,
        'monto_de_la_hoja_eic' => '2455',
    ],
    'pasos' => [
        [
            'formulario' => 'hoja-eic',
            'linea' => 'tabla',
            'texto' => 'Con el monto de USD 2.455 se busca la franja 2.450 a 2.500; columna "Single, head of household, or qualifying surviving spouse" con 1 hijo: USD 842.',
            'valor' => '842',
            'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 16 (ejemplo al inicio de la EIC Table)', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf'],
        ],
    ],
    'lineas' => [
        'hoja-eic' => ['tabla' => '842'],
    ],
    'cubre' => [],
    'contraste' => null,
];
