<?php

// 6.9b. Deducción estándar de un matrimonio que declara en conjunto con los dos nacidos antes del 2 de enero de 1961 y ninguno ciego.
// Nadie puede reclamarlos como dependientes, así que se usa la tabla (no la hoja de dependientes). La deducción para mayores del Schedule 1-A
// (línea 13b del 1040) es otra cosa y no entra en este caso.
// La línea 12d del 1040 son casillas; aquí su "valor" es el número de casillas marcadas (el mismo número que pide la tabla).
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'deduccion-estandar-65-mfj',
    'anio' => 2025,
    'descripcion' => 'Matrimonio que declara en conjunto, nacidos en 1955 y 1958, ninguno ciego ni dependiente de otro: dos casillas en la línea 12d y deducción estándar de USD 34.700 en la línea 12e.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'fecha_nacimiento_contribuyente' => '1955-04-10',
        'fecha_nacimiento_conyuge' => '1958-09-02',
        'contribuyente_ciego' => 'no',
        'conyuge_ciego' => 'no',
        'alguien_puede_reclamarlos_como_dependientes' => 'no',
        'detalla_deducciones' => 'no',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '12d',
            'texto' => 'Casillas de la línea 12d: "You: Were born before January 2, 1961" (sí) y "Spouse: Was born before January 2, 1961" (sí); ninguna de ceguera. Total: 2.',
            'valor' => '2',
            'fuente' => $fuente('f1040', 'p. 2 (línea 12d)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '12e-base',
            'texto' => 'Deducción estándar base de casados en conjunto: USD 31.500. Como hay casillas marcadas en la 12d, el margen manda a las instrucciones.',
            'valor' => '31500',
            'fuente' => $fuente('f1040', 'p. 2 (margen de las líneas 12e a 15, Standard deduction for)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '12e',
            'texto' => 'Standard Deduction Chart for People Who Were Born Before January 2, 1961, or Were Blind: casados en conjunto con 2 casillas → USD 34.700 (31.500 + 2 × 1.600).',
            'valor' => '34700',
            'fuente' => $fuente('i1040gi', 'p. 35 (Standard Deduction Chart for People Who Were Born Before January 2, 1961, or Were Blind)'),
        ],
    ],
    'lineas' => [
        '1040' => ['12d' => '2', '12e' => '34700'],
    ],
    'cubre' => ['1040:12d', '1040:12e'],
    'contraste' => null,
];
