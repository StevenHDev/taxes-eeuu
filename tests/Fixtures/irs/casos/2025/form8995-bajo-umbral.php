<?php

// 6.9b. Deducción QBI simple (Form 8995) con ingreso gravable muy por debajo del umbral (USD 197.300 soltero).
// Soltero con USD 40.000 de salarios W-2 (casilla 3 igual) y USD 18.400 de ganancia en el Schedule C; sin dividendos, ganancias de capital,
// REIT/PTP, pérdidas arrastradas, seguro médico de autoempleo ni aportes a planes de retiro. Deducción estándar (USD 15.750) y sin Schedule 1-A.
// El QBI es la ganancia del Schedule C MENOS la parte deducible del impuesto de autoempleo (i8995 p. 2, Determining Your Qualified Business Income).
// Schedule SE: 18.400 × 0,9235 = 16.992,40 → 16.992; 10 = 16.992 × 0,124 = 2.107,008 → 2.107 (con centavos 2.107,06); 11 = 492,768 → 493 (492,78);
// 12 = 2.600 (2.599,84); 13 = 1.300 (1.299,92). Las dos lecturas del redondeo (i1040gi p. 23) dan el mismo dólar entero.
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'form8995-bajo-umbral',
    'anio' => 2025,
    'descripcion' => 'Soltero con USD 40.000 de salarios y USD 18.400 de ganancia en el Schedule C: QBI de USD 17.100 (ganancia menos la mitad del impuesto de autoempleo) y deducción de USD 3.420 en la línea 15 del 8995 y la 13a del 1040.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'w2_casilla_1' => '40000',
        'w2_casilla_3' => '40000',
        'ganancia_neta_schedule_c_linea_31' => '18400',
        'negocio_es_de_servicios_especificados_sstb' => 'no',
        'seguro_medico_autoempleo' => '0',
        'aportes_retiro_autoempleo' => '0',
        'perdida_qbi_arrastrada' => '0',
        'dividendos_calificados_y_ganancia_de_capital' => '0',
        'reit_ptp' => '0',
        'detalla_deducciones' => 'no',
        'schedule_1a' => '0',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-se',
            'linea' => '12',
            'texto' => 'Impuesto de autoempleo sobre los 18.400: 4a = 16.992; 10 = 2.107; 11 = 493; 12 = 2.600 (la línea 9 es 176.100 − 40.000 = 136.100, no limita).',
            'valor' => '2600',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, líneas 4a a 12)'),
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '15',
            'texto' => 'Parte deducible del impuesto de autoempleo: 2.600 × 50 % = USD 1.300.',
            'valor' => '1300',
            'fuente' => $fuente('f1040sse', 'p. 1 (Part I, línea 13)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '11a',
            'texto' => 'Ingreso bruto ajustado: salarios 40.000 + Schedule 1 línea 3 (18.400) − Schedule 1 línea 15 (1.300) = USD 57.100.',
            'valor' => '57100',
            'fuente' => $fuente('f1040', 'p. 1 (líneas 1z, 8, 9, 10 y 11a)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '12e',
            'texto' => 'Deducción estándar de soltero: USD 15.750.',
            'valor' => '15750',
            'fuente' => $fuente('f1040', 'p. 2 (margen de las líneas 12e a 15, Single or Married filing separately)'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => 'umbral',
            'texto' => 'Se puede usar el 8995 simple porque el ingreso gravable antes de la deducción (41.350) no pasa de USD 197.300 (soltero).',
            'valor' => '197300',
            'fuente' => $fuente('f8995', 'p. 1 (encabezado: "at or below $197,300")'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '1c',
            'texto' => 'QBI del negocio: ganancia del Schedule C (18.400) menos la parte deducible del impuesto de autoempleo (1.300) = USD 17.100. Sin seguro médico ni aportes a retiro que restar.',
            'valor' => '17100',
            'fuente' => $fuente('i8995', 'p. 2 (Determining Your Qualified Business Income: "deductible part of self-employment tax")'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '2',
            'texto' => 'Total de QBI (un solo negocio): USD 17.100.',
            'valor' => '17100',
            'fuente' => $fuente('f8995', 'p. 1 (línea 2)'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '4',
            'texto' => 'Líneas 2 y 3 (sin pérdida arrastrada): USD 17.100.',
            'valor' => '17100',
            'fuente' => $fuente('f8995', 'p. 1 (líneas 3 y 4)'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '5',
            'texto' => 'Componente QBI: 17.100 × 20 % = USD 3.420.',
            'valor' => '3420',
            'fuente' => $fuente('f8995', 'p. 1 (línea 5)'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '10',
            'texto' => 'Líneas 5 y 9 (sin REIT ni PTP, la 9 es 0): USD 3.420.',
            'valor' => '3420',
            'fuente' => $fuente('f8995', 'p. 1 (líneas 6 a 10)'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '11',
            'texto' => 'Ingreso gravable antes de la deducción QBI: 1040 línea 11a menos líneas 12e y 13b = 57.100 − 15.750 − 0 = USD 41.350.',
            'valor' => '41350',
            'fuente' => $fuente('i8995', 'p. 4 (Line 11: "Form 1040 ... line 11a, minus lines 12e and 13b")'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '13',
            'texto' => 'Línea 11 menos línea 12 (sin ganancia de capital ni dividendos calificados): USD 41.350.',
            'valor' => '41350',
            'fuente' => $fuente('f8995', 'p. 1 (líneas 12 y 13)'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '14',
            'texto' => 'Límite por ingreso: 41.350 × 20 % = USD 8.270.',
            'valor' => '8270',
            'fuente' => $fuente('f8995', 'p. 1 (línea 14)'),
        ],
        [
            'formulario' => 'f8995',
            'linea' => '15',
            'texto' => 'Deducción QBI: la menor entre la línea 10 (3.420) y la 14 (8.270) = USD 3.420.',
            'valor' => '3420',
            'fuente' => $fuente('f8995', 'p. 1 (línea 15)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '13a',
            'texto' => 'Deducción por ingresos calificados del negocio, del Form 8995 línea 15: USD 3.420.',
            'valor' => '3420',
            'fuente' => $fuente('i8995', 'p. 5 (Line 15: "Enter this amount on your Form 1040 ... line 13a")'),
        ],
    ],
    'lineas' => [
        'sch-se' => ['12' => '2600'],
        'sch-1' => ['15' => '1300'],
        '1040' => ['11a' => '57100', '12e' => '15750', '13a' => '3420'],
        'f8995' => ['2' => '17100', '4' => '17100', '5' => '3420', '10' => '3420', '11' => '41350', '13' => '41350', '14' => '8270', '15' => '3420'],
    ],
    'cubre' => [
        'f8995:2', 'f8995:4', 'f8995:5', 'f8995:10', 'f8995:11', 'f8995:13', 'f8995:14', 'f8995:15', '1040:13a',
    ],
    'contraste' => null,
];
