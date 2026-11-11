<?php

// 6.9b. Pagos y resultado (líneas 25a a 26 y 33 a 37 del 1040): retención de W-2, retención de respaldo de un 1099-INT,
// cuatro pagos estimados del 1040-ES de 2025 (el cuarto pagado el 15 de enero de 2026, que SÍ es de 2025) y un pago del 1040-ES de 2024
// hecho el 15 de enero de 2025, que NO cuenta: la línea 26 lleva los pagos estimados "for 2025" (i1040gi p. 39), no los hechos en 2025.
// Sin sobrepago de 2024 aplicado a 2025. Sin créditos reembolsables (soltero sin hijos con AGI de 60.300: no hay EIC).
// El cliente quiere que le devuelvan todo el sobrepago (nada a 2026). La línea 35a no está como tal en el catálogo (allí es "35a-35d"):
// va en "lineas" pero no en "cubre".
$fuente = fn (string $doc, string $pagina) => ['doc' => $doc, 'pagina' => $pagina, 'url' => "https://www.irs.gov/pub/irs-prior/{$doc}--2025.pdf"];

return [
    'codigo' => 'pagos-estimados-y-retenciones',
    'anio' => 2025,
    'descripcion' => 'Soltero con USD 60.000 de salarios y USD 300 de intereses: impuesto de USD 5.111; retenciones de USD 4.172 (W-2 y 1099-INT) y USD 2.000 de estimados de 2025 (el pago del 1040-ES de 2024 no cuenta): pagó de más USD 1.061.',
    'entradas' => [
        'estado_civil' => 'soltero',
        'w2' => [['casilla_1' => '60000', 'casilla_2' => '4100']],
        '1099_int' => [['casilla_1' => '300', 'casilla_4' => '72']],
        'comprobantes_de_pago' => [
            ['tipo' => '1040-ES', 'anio_fiscal' => 2024, 'fecha' => '2025-01-15', 'monto' => '600'],
            ['tipo' => '1040-ES', 'anio_fiscal' => 2025, 'fecha' => '2025-04-15', 'monto' => '500'],
            ['tipo' => '1040-ES', 'anio_fiscal' => 2025, 'fecha' => '2025-06-16', 'monto' => '500'],
            ['tipo' => '1040-ES', 'anio_fiscal' => 2025, 'fecha' => '2025-09-15', 'monto' => '500'],
            ['tipo' => '1040-ES', 'anio_fiscal' => 2025, 'fecha' => '2026-01-15', 'monto' => '500'],
        ],
        'sobrepago_2024_aplicado_a_2025' => '0',
        'dependientes' => 0,
        'detalla_deducciones' => 'no',
        'parte_del_reembolso_a_2026' => '0',
    ],
    'pasos' => [
        [
            'formulario' => '1040',
            'linea' => '15',
            'texto' => 'Ingreso total 60.000 + 300 = 60.300 (= 11a = 11b); menos la deducción estándar de soltero (15.750) = USD 44.550.',
            'valor' => '44550',
            'fuente' => $fuente('f1040', 'p. 1 y p. 2 (líneas 1a, 2b, 9, 11a, 12e, 14 y 15)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '16',
            'texto' => 'Tax Table, fila 44.550–44.600, columna "Single": USD 5.111.',
            'valor' => '5111',
            'fuente' => $fuente('p1040', 'p. 7 (Tax Table, fila 44,550–44,600)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '24',
            'texto' => 'Sin Schedule 2, sin créditos (líneas 17, 19, 20 y 23 en cero): 18 = 22 = 24 = USD 5.111.',
            'valor' => '5111',
            'fuente' => $fuente('f1040', 'p. 2 (líneas 16 a 24)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '25a',
            'texto' => 'Retención federal de los W-2 (casilla 2): USD 4.100.',
            'valor' => '4100',
            'fuente' => $fuente('i1040gi', 'p. 39 (Line 25a—Form(s) W-2)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '25b',
            'texto' => 'Retención federal de los 1099 (casilla 4 del 1099-INT, retención de respaldo sobre intereses): USD 72.',
            'valor' => '72',
            'fuente' => $fuente('i1040gi', 'p. 39 (Line 25b—Form(s) 1099: "taxable or tax-exempt interest income ... box 4 of Form 1099")'),
        ],
        [
            'formulario' => '1040',
            'linea' => '25d',
            'texto' => 'Líneas 25a a 25c (25c en cero): 4.100 + 72 = USD 4.172.',
            'valor' => '4172',
            'fuente' => $fuente('f1040', 'p. 2 (línea 25d)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '26',
            'texto' => 'Pagos estimados para 2025: los cuatro del 1040-ES de 2025 (500 × 4, incluido el del 15 de enero de 2026) = 2.000. El de USD 600 es del 1040-ES de 2024 (cuarta cuota de 2024, pagada en enero de 2025): va en la declaración de 2024, no aquí. Sin sobrepago de 2024 aplicado. Total: USD 2.000.',
            'valor' => '2000',
            'fuente' => $fuente('i1040gi', 'p. 39 (Line 26, 2025 Estimated Tax Payments: "payments you made for 2025")'),
        ],
        [
            'formulario' => '1040',
            'linea' => '32',
            'texto' => 'Otros pagos y créditos reembolsables (27a, 28, 29, 30 y 31): USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040', 'p. 2 (línea 32)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '33',
            'texto' => 'Pagos totales: líneas 25d, 26 y 32 = 4.172 + 2.000 + 0 = USD 6.172.',
            'valor' => '6172',
            'fuente' => $fuente('f1040', 'p. 2 (línea 33)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '34',
            'texto' => 'La línea 33 (6.172) es mayor que la 24 (5.111): pagó de más 6.172 − 5.111 = USD 1.061.',
            'valor' => '1061',
            'fuente' => $fuente('f1040', 'p. 2 (línea 34)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '35a',
            'texto' => 'Parte del sobrepago que se devuelve (el cliente pide todo): USD 1.061.',
            'valor' => '1061',
            'fuente' => $fuente('f1040', 'p. 2 (línea 35a)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '36',
            'texto' => 'Parte que se aplica al estimado de 2026: USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040', 'p. 2 (línea 36)'),
        ],
        [
            'formulario' => '1040',
            'linea' => '37',
            'texto' => 'Monto que se debe: la línea 33 no es menor que la 24, así que no se debe nada: USD 0.',
            'valor' => '0',
            'fuente' => $fuente('f1040', 'p. 2 (línea 37)'),
        ],
    ],
    'lineas' => [
        '1040' => [
            '15' => '44550', '16' => '5111', '24' => '5111', '25a' => '4100', '25b' => '72', '25d' => '4172', '26' => '2000',
            '32' => '0', '33' => '6172', '34' => '1061', '35a' => '1061', '36' => '0', '37' => '0',
        ],
    ],
    'cubre' => [
        '1040:24', '1040:25a', '1040:25b', '1040:25d', '1040:26', '1040:32', '1040:33', '1040:34', '1040:36', '1040:37',
    ],
    'contraste' => null,
];
