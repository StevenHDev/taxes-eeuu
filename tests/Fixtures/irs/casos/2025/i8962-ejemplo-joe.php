<?php

// Ejemplo del IRS (Allocation Situation 4, Joe y Alice). Transcrito a mano. La línea 4 no la dice el ejemplo:
// sale de la Tabla 1-1 (tamaño 2) y coincide con el "380%" que el texto afirma (77.672 / 20.440 = 3,8).
return [
    'codigo' => 'i8962-ejemplo-joe',
    'anio' => 2025,
    'descripcion' => 'Ejemplo de las instrucciones del 8962: Joe, con tamaño de familia 2, ingreso del hogar USD 77.672, prima de inscripción USD 10.400 (80 % de 13.000), SLCSP USD 9.600 (80 % de 12.000) y APTC USD 5.076 (80 % de 6.345).',
    'entradas' => [
        'tamano_familia' => 2,
        'estado_residencia' => 'estados contiguos (Tabla 1-1)',
        'ingreso_del_hogar' => '77672',
        'primas_de_inscripcion_asignadas' => '10400',
        'slcsp_aplicable_asignada' => '9600',
        'aptc_asignado' => '5076',
    ],
    'pasos' => [
        [
            'formulario' => '8962',
            'linea' => '4',
            'texto' => 'Línea de pobreza para tamaño de familia 2 en los 48 estados contiguos (Tabla 1-1): USD 20.440.',
            'valor' => '20440',
            'fuente' => ['doc' => 'i8962', 'pagina' => 'p. 8 (Tabla 1-1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i8962--2025.pdf'],
        ],
        [
            'formulario' => '8962',
            'linea' => '5',
            'texto' => 'El ingreso del hogar de USD 77.672 es el 380 % de la línea de pobreza de una familia de dos.',
            'valor' => '380',
            'fuente' => ['doc' => 'i8962', 'pagina' => 'p. 21 (Example 1, Allocation Situation 4)', 'url' => 'https://www.irs.gov/pub/irs-prior/i8962--2025.pdf'],
        ],
        [
            'formulario' => '8962',
            'linea' => '7',
            'texto' => 'Cifra aplicable para el 380 % (Tabla 2): 0,0800.',
            'valor' => '0.0800',
            'fuente' => ['doc' => 'i8962', 'pagina' => 'p. 11 (Tabla 2) y p. 22 (el ejemplo multiplica por 0,0800)', 'url' => 'https://www.irs.gov/pub/irs-prior/i8962--2025.pdf'],
        ],
        [
            'formulario' => '8962',
            'linea' => '8a',
            'texto' => 'Monto de contribución anual: 77.672 x 0,0800 = USD 6.214 (cifra del ejemplo).',
            'valor' => '6214',
            'fuente' => ['doc' => 'i8962', 'pagina' => 'p. 22 (Example 1, continuación)', 'url' => 'https://www.irs.gov/pub/irs-prior/i8962--2025.pdf'],
        ],
        [
            'formulario' => '8962',
            'linea' => '24',
            'texto' => 'Crédito del año: el menor entre USD 3.386 (SLCSP 9.600 menos contribución 6.214) y USD 10.400 (primas de inscripción) = USD 3.386.',
            'valor' => '3386',
            'fuente' => ['doc' => 'i8962', 'pagina' => 'p. 21 y 22 (Example 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i8962--2025.pdf'],
        ],
        [
            'formulario' => '8962',
            'linea' => '25',
            'texto' => 'Pago adelantado del crédito (APTC) que Joe debe conciliar: 6.345 x 0,80 = USD 5.076.',
            'valor' => '5076',
            'fuente' => ['doc' => 'i8962', 'pagina' => 'p. 21 (Example 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i8962--2025.pdf'],
        ],
        [
            'formulario' => '8962',
            'linea' => '27',
            'texto' => 'APTC en exceso: 5.076 menos 3.386 = USD 1.690.',
            'valor' => '1690',
            'fuente' => ['doc' => 'i8962', 'pagina' => 'p. 22 (Example 1)', 'url' => 'https://www.irs.gov/pub/irs-prior/i8962--2025.pdf'],
        ],
    ],
    'lineas' => [
        '8962' => ['4' => '20440', '5' => '380', '7' => '0.0800', '8a' => '6214', '24' => '3386', '25' => '5076', '27' => '1690'],
    ],
    'cubre' => ['8962:4', '8962:5', '8962:7', '8962:8a', '8962:24', '8962:25', '8962:27'],
    'contraste' => null,
];
