<?php

// 6.9b. Oficina en casa por el método simplificado (Simplified Method Worksheet, i1040sc p. 11 y 12) SIN que mande el límite de la ganancia:
// la oficina mide 360 pies cuadrados, pero la hoja no deja pasar de 300 (línea 2), así que también prueba el tope de pies cuadrados.
// Todo el ingreso del negocio sale de esta oficina (línea 1 de la hoja = línea 29 del Schedule C). Sin arrastres de años anteriores (líneas 6a y 6b en cero).
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'schedule-c-oficina-simplificada',
    'anio' => 2025,
    'descripcion' => 'Diseñadora con USD 40.000 de ingreso bruto y USD 8.000 de gastos que usa todo el año, solo para el negocio, 360 pies cuadrados de su casa de 1.500: el método simplificado da USD 1.500 (tope de 300 pies a USD 5) y la línea 31 queda en USD 30.500.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'dueno_del_negocio' => 'contribuyente',
        '1099_nec' => [['casilla_1' => '40000']],
        'total_gastos_lineas_8_a_27b' => '8000',
        'oficina_en_casa' => 'si',
        'metodo_oficina' => 'simplificado',
        'pies_cuadrados_de_la_casa' => '1500',
        'pies_cuadrados_de_la_oficina' => '360',
        'uso_exclusivo_y_regular_todo_el_anio' => 'si',
        'es_guarderia' => 'no',
        'todo_el_ingreso_sale_de_la_oficina_en_casa' => 'si',
        'ganancias_o_perdidas_form_4797_o_8949' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-c',
            'linea' => '7',
            'texto' => 'Ingreso bruto (línea 1 = casilla 1 del 1099-NEC, sin devoluciones, costo de mercancía ni otros ingresos): USD 40.000.',
            'valor' => '40000',
            'fuente' => $fuente('f1040sc', 'p. 1 (Part I, líneas 1 a 7)'),
        ],
        [
            'formulario' => 'sch-c',
            'linea' => '28',
            'texto' => 'Total de gastos antes de la oficina en casa (dato de entrada): USD 8.000.',
            'valor' => '8000',
            'fuente' => $fuente('f1040sc', 'p. 1 (línea 28)'),
        ],
        [
            'formulario' => 'sch-c',
            'linea' => '29',
            'texto' => 'Ganancia provisional: 40.000 − 8.000 = USD 32.000.',
            'valor' => '32000',
            'fuente' => $fuente('f1040sc', 'p. 1 (línea 29)'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '1',
            'texto' => 'Límite por el ingreso: todo el ingreso sale de la oficina en casa, así que A = línea 29 (32.000), B = 0, C = 32.000, D = 0 y E = 32.000.',
            'valor' => '32000',
            'fuente' => $fuente('i1040sc', 'p. 11 (Instructions for the Simplified Method Worksheet, Line 1, renglones A a E)'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '2',
            'texto' => 'Pies cuadrados permitidos: el menor entre los usados (360) y 300 = 300.',
            'valor' => '300',
            'fuente' => $fuente('i1040sc', 'p. 11 (Line 2: "enter the smaller of the square feet you actually used or 300")'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '3c',
            'texto' => 'Monto por pie cuadrado: 3a (USD 5) por 3b (1.0, no es guardería) = USD 5,00.',
            'valor' => '5',
            'fuente' => $fuente('i1040sc', 'p. 12 (Simplified Method Worksheet, líneas 3a a 3c)'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '4',
            'texto' => 'Línea 2 por línea 3c: 300 × 5 = USD 1.500.',
            'valor' => '1500',
            'fuente' => $fuente('i1040sc', 'p. 12 (Simplified Method Worksheet, línea 4)'),
        ],
        [
            'formulario' => 'hoja-oficina-simplificada',
            'linea' => '5',
            'texto' => 'Gasto permitido: el menor entre la línea 1 (32.000) y la línea 4 (1.500) = USD 1.500. Va a la línea 30 del Schedule C.',
            'valor' => '1500',
            'fuente' => $fuente('i1040sc', 'p. 12 (Simplified Method Worksheet, línea 5)'),
        ],
        [
            'formulario' => 'sch-c',
            'linea' => '30',
            'texto' => 'Oficina en casa por el método simplificado: USD 1.500 (en el formulario se anotan 1.500 pies de la casa y 360 de la oficina).',
            'valor' => '1500',
            'fuente' => $fuente('f1040sc', 'p. 1 (línea 30, Simplified method filers only)'),
        ],
        [
            'formulario' => 'sch-c',
            'linea' => '31',
            'texto' => 'Ganancia neta: línea 29 menos línea 30 = 32.000 − 1.500 = USD 30.500.',
            'valor' => '30500',
            'fuente' => $fuente('f1040sc', 'p. 1 (línea 31)'),
        ],
    ],
    'lineas' => [
        'sch-c' => ['7' => '40000', '28' => '8000', '29' => '32000', '30' => '1500', '31' => '30500'],
        'hoja-oficina-simplificada' => ['1' => '32000', '2' => '300', '4' => '1500', '5' => '1500'],
    ],
    'cubre' => ['sch-c:29', 'sch-c:30', 'sch-c:31'],
    'contraste' => null,
];
