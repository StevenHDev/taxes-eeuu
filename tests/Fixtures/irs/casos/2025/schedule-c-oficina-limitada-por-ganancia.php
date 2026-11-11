<?php

// 6.9b. Oficina en casa por el método simplificado CON el límite de la ganancia: la hoja da USD 1.000 (200 pies × USD 5),
// pero la línea 1 de la hoja (ganancia de la línea 29) es solo USD 800, así que la línea 30 queda en 800 y la línea 31 en cero.
// Con el método simplificado, lo que el límite no deja deducir no pasa a otro año (i1040sc p. 13, Gross income limitation: "you can't carry over the difference to another tax year").
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-c-oficina-limitada-por-ganancia',
    'anio' => 2025,
    'descripcion' => 'Negocio pequeño con USD 6.000 de ingreso bruto y USD 5.200 de gastos que usa 200 pies cuadrados de la casa: el método simplificado daría USD 1.000, pero el límite de la ganancia lo deja en USD 800 y la línea 31 queda en cero.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'dueno_del_negocio' => 'contribuyente',
        '1099_nec' => [['casilla_1' => '6000']],
        'total_gastos_lineas_8_a_27b' => '5200',
        'oficina_en_casa' => 'si',
        'metodo_oficina' => 'simplificado',
        'pies_cuadrados_de_la_casa' => '1200',
        'pies_cuadrados_de_la_oficina' => '200',
        'uso_exclusivo_y_regular_todo_el_anio' => 'si',
        'es_guarderia' => 'no',
        'todo_el_ingreso_sale_de_la_oficina_en_casa' => 'si',
        'ganancias_o_perdidas_form_4797_o_8949' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-c',
            'linea' => '7',
            'texto' => 'Ingreso bruto (casilla 1 del 1099-NEC, sin devoluciones, costo de mercancía ni otros ingresos): USD 6.000.',
            'valor' => '6000',
            'fuente' => $fuente('f1040sc', 'p. 1 (Part I, líneas 1 a 7)'),
        ],
        [
            'formulario' => 'sch-c',
            'linea' => '28',
            'texto' => 'Total de gastos antes de la oficina en casa (dato de entrada): USD 5.200.',
            'valor' => '5200',
            'fuente' => $fuente('f1040sc', 'p. 1 (línea 28)'),
        ],
        [
            'formulario' => 'sch-c',
            'linea' => '29',
            'texto' => 'Ganancia provisional: 6.000 − 5.200 = USD 800.',
            'valor' => '800',
            'fuente' => $fuente('f1040sc', 'p. 1 (línea 29)'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '1',
            'texto' => 'Límite por el ingreso: A = línea 29 (800), B = 0, C = 800, D = 0, E = 800.',
            'valor' => '800',
            'fuente' => $fuente('i1040sc', 'p. 11 (Instructions for the Simplified Method Worksheet, Line 1, renglones A a E)'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '2',
            'texto' => 'Pies cuadrados permitidos: el menor entre los usados (200) y 300 = 200.',
            'valor' => '200',
            'fuente' => $fuente('i1040sc', 'p. 11 (Line 2)'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '4',
            'texto' => 'Línea 2 por línea 3c (USD 5 × 1.0 = 5,00): 200 × 5 = USD 1.000.',
            'valor' => '1000',
            'fuente' => $fuente('i1040sc', 'p. 12 (Simplified Method Worksheet, líneas 3a a 4)'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '5',
            'texto' => 'Gasto permitido: el menor entre la línea 1 (800) y la línea 4 (1.000) = USD 800. Va a la línea 30 del Schedule C.',
            'valor' => '800',
            'fuente' => $fuente('i1040sc', 'p. 12 (Simplified Method Worksheet, línea 5)'),
        ],
        [
            'formulario' => 'sch-c',
            'linea' => '30',
            'texto' => 'Oficina en casa: USD 800 (limitada por la ganancia). Los USD 200 que no se deducen no pasan a otro año.',
            'valor' => '800',
            'fuente' => $fuente('i1040sc', 'p. 13 (Gross income limitation, método simplificado)'),
        ],
        [
            'formulario' => 'sch-c',
            'linea' => '31',
            'texto' => 'Ganancia neta: 800 − 800 = USD 0. Con cero no hay que ir al Schedule SE (la línea 4c sería menor de USD 400).',
            'valor' => '0',
            'fuente' => $fuente('f1040sc', 'p. 1 (línea 31)'),
        ],
    ],
    'lineas' => [
        'sch-c' => ['7' => '6000', '28' => '5200', '29' => '800', '30' => '800', '31' => '0'],
        'hoja-oficina-simplificada' => ['1' => '800', '2' => '200', '4' => '1000', '5' => '800'],
    ],
    'cubre' => ['sch-c:29', 'sch-c:30', 'sch-c:31'],
    'contraste' => null,
];
