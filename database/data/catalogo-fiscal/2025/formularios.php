<?php

/**
 * Formularios del año fiscal 2025 (documento madre).
 *
 * Origen: docs/mejora-plataforma/documento-madre.md, sección 4.0-B (26 formularios: solo el 1040
 * es siempre; las fichas completas son 1040, Schedule C, SE, 8812, 2441, EIC y 8962; Schedules 1,
 * 2 y 3 son conectores; el resto queda pendiente sin líneas), y las líneas de las secciones 4.1
 * a 4.8 (W-2, Schedule C, SE, 8812, 2441, EIC, 8962 y ejemplo de María).
 * Líneas que el documento madre nombra sin detalle de origen se marcan como 'calculo'.
 */
return [
    // ── Form 1040 (siempre) ──
    [
        'codigo' => '1040', 'nombre' => 'Form 1040', 'para_que' => 'La declaración ("front")',
        'siempre' => true, 'ficha' => 'completa', 'orden' => 1,
        'fuente_texto' => 'Form 1040 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040.pdf',
        'lineas' => [
            ['linea' => '1a', 'descripcion' => 'Salarios (suma de la casilla 1 de todos los W-2)', 'origen_tipo' => 'casilla', 'origen_clave' => 'w2:1'],
            ['linea' => '1c', 'descripcion' => 'Propinas no reportadas (Form 4137, ficha pendiente)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '1e', 'descripcion' => 'Parte gravable de beneficios de cuidado de dependientes (Form 2441, Parte III)', 'origen_tipo' => 'linea', 'origen_clave' => 'f2441:parte-iii'],
            ['linea' => '8', 'descripcion' => 'Otros ingresos del Schedule 1', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-1:10'],
            ['linea' => '11a', 'descripcion' => 'Ingreso ajustado', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '12', 'descripcion' => 'Deducción estándar', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '13b', 'descripcion' => 'Deducciones del Schedule 1-A (horas extra, propinas, intereses del vehículo, mayores)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '19', 'descripcion' => 'Crédito por hijos y otros dependientes (Schedule 8812)', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-8812:14'],
            ['linea' => '20', 'descripcion' => 'Otros créditos no reembolsables del Schedule 3', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-3:8'],
            ['linea' => '23', 'descripcion' => 'Otros impuestos del Schedule 2', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-2:4;sch-2:1a'],
            ['linea' => '25a', 'descripcion' => 'Retención federal de W-2 (suma de la casilla 2)', 'origen_tipo' => 'casilla', 'origen_clave' => 'w2:2'],
            ['linea' => '26', 'descripcion' => 'Pagos estimados', 'origen_tipo' => 'respuesta', 'origen_clave' => 'G1'],
            ['linea' => '27a', 'descripcion' => 'Crédito por ingreso del trabajo (Schedule EIC)', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-eic:credito'],
            ['linea' => '28', 'descripcion' => 'Parte reembolsable del crédito por hijos (ACTC, Schedule 8812)', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-8812:27'],
            ['linea' => '31', 'descripcion' => 'Crédito neto por prima del Marketplace y pago con la extensión', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-3:9;sch-3:10'],
        ],
    ],

    // ── Conectores: Schedules 1, 2 y 3 ──
    [
        'codigo' => 'sch-1', 'nombre' => 'Schedule 1', 'para_que' => 'Ingresos adicionales y ajustes',
        'siempre' => false, 'ficha' => 'conector', 'orden' => 2,
        'fuente_texto' => 'Schedule 1 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040s1.pdf',
        'lineas' => [
            ['linea' => '3', 'descripcion' => 'Ganancia o pérdida de negocio (Schedule C, línea 31)', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-c:31'],
            ['linea' => '10', 'descripcion' => 'Total de otros ingresos (suma de las líneas 1 a 9)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '15', 'descripcion' => 'Mitad del impuesto de autoempleo, deducible (Schedule SE, línea 13)', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-se:13'],
        ],
    ],
    [
        'codigo' => 'sch-2', 'nombre' => 'Schedule 2', 'para_que' => 'Otros impuestos',
        'siempre' => false, 'ficha' => 'conector', 'orden' => 3,
        'fuente_texto' => 'Schedule 2 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040s2.pdf',
        'lineas' => [
            ['linea' => '4', 'descripcion' => 'Impuesto de autoempleo (Schedule SE, línea 12)', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-se:12'],
            ['linea' => '1a', 'descripcion' => 'Devolución por adelanto de más del crédito (Form 8962, línea 29)', 'origen_tipo' => 'linea', 'origen_clave' => 'f8962:29'],
        ],
    ],
    [
        'codigo' => 'sch-3', 'nombre' => 'Schedule 3', 'para_que' => 'Otros créditos y pagos',
        'siempre' => false, 'ficha' => 'conector', 'orden' => 4,
        'fuente_texto' => 'Schedule 3 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040s3.pdf',
        'lineas' => [
            ['linea' => '2', 'descripcion' => 'Crédito por cuidado de hijos y dependientes (Form 2441, línea 11)', 'origen_tipo' => 'linea', 'origen_clave' => 'f2441:11'],
            ['linea' => '8', 'descripcion' => 'Total de créditos no reembolsables (suma de las líneas 1 a 7)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '9', 'descripcion' => 'Crédito neto por prima del Marketplace (Form 8962, línea 26)', 'origen_tipo' => 'linea', 'origen_clave' => 'f8962:26'],
            ['linea' => '10', 'descripcion' => 'Pago con la solicitud de extensión', 'origen_tipo' => 'respuesta', 'origen_clave' => 'G2'],
        ],
    ],

    // ── Fichas completas ──
    [
        'codigo' => 'sch-c', 'nombre' => 'Schedule C', 'para_que' => 'Ganancia o pérdida de un negocio propio',
        'siempre' => false, 'ficha' => 'completa', 'orden' => 5,
        'fuente_texto' => 'Schedule C de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040sc.pdf',
        'lineas' => [
            ['linea' => '1', 'descripcion' => 'Ingresos brutos: 1099-NEC (casilla 1) + 1099-K (casilla 1a) + ingresos sin formulario', 'origen_tipo' => 'casilla', 'origen_clave' => '1099-nec:1;1099-k:1a'],
            ['linea' => '4', 'descripcion' => 'Costo de la mercancía vendida (si vende productos)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '8-27', 'descripcion' => 'Gastos por categoría: vehículo, contratistas, seguros, renta, materiales, servicios, etc.', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '28', 'descripcion' => 'Total de gastos', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '30', 'descripcion' => 'Oficina en casa (Form 8829 o método simplificado)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '31', 'descripcion' => 'Ganancia o pérdida neta', 'origen_tipo' => 'calculo', 'origen_clave' => null],
        ],
    ],
    [
        'codigo' => 'sch-se', 'nombre' => 'Schedule SE', 'para_que' => 'Impuesto de autoempleo',
        'siempre' => false, 'ficha' => 'completa', 'orden' => 6,
        'fuente_texto' => 'Schedule SE de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040sse.pdf',
        'lineas' => [
            ['linea' => '2', 'descripcion' => 'Ganancia neta del Schedule C (línea 31)', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-c:31'],
            ['linea' => '4a', 'descripcion' => 'Base: línea 3 × 92,35 %', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '12', 'descripcion' => 'Impuesto de autoempleo', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '13', 'descripcion' => 'Mitad del impuesto, deducible', 'origen_tipo' => 'calculo', 'origen_clave' => null],
        ],
    ],
    [
        'codigo' => 'sch-8812', 'nombre' => 'Schedule 8812', 'para_que' => 'Crédito por hijos y por otros dependientes',
        'siempre' => false, 'ficha' => 'completa', 'orden' => 7,
        'fuente_texto' => 'Schedule 8812 de 2025 e instrucciones', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040s8.pdf',
        'lineas' => [
            ['linea' => '1', 'descripcion' => 'Ingreso ajustado (1040, línea 11a)', 'origen_tipo' => 'linea', 'origen_clave' => '1040:11a'],
            ['linea' => '4', 'descripcion' => 'Número de hijos menores de 17 con SSN válido', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C2;C9'],
            ['linea' => '5', 'descripcion' => 'Hijos × USD 2.200', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '6', 'descripcion' => 'Número de otros dependientes (con ITIN o ATIN, o de 17 años o más)', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C9'],
            ['linea' => '14', 'descripcion' => 'Crédito por hijos y otros dependientes', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '18a', 'descripcion' => 'Ingreso del trabajo (W-2 casilla 1 + ganancia del Schedule C)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '27', 'descripcion' => 'Parte reembolsable (ACTC)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
        ],
    ],
    [
        'codigo' => 'f2441', 'nombre' => 'Form 2441', 'para_que' => 'Crédito por cuidado de hijos y dependientes',
        'siempre' => false, 'ficha' => 'completa', 'orden' => 8,
        'fuente_texto' => 'Form 2441 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f2441.pdf',
        'lineas' => [
            ['linea' => 'parte-i', 'descripcion' => 'Proveedor del cuidado: nombre, dirección, SSN o EIN, y monto pagado', 'origen_tipo' => 'respuesta', 'origen_clave' => 'E6'],
            ['linea' => 'parte-ii', 'descripcion' => 'Personas cuidadas: nombre, SSN y gastos', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C2'],
            ['linea' => 'parte-iii', 'descripcion' => 'Beneficios de cuidado de dependientes del W-2 (casilla 10)', 'origen_tipo' => 'casilla', 'origen_clave' => 'w2:10'],
            ['linea' => '11', 'descripcion' => 'Crédito por cuidado de hijos y dependientes', 'origen_tipo' => 'calculo', 'origen_clave' => null],
        ],
    ],
    [
        'codigo' => 'sch-eic', 'nombre' => 'Schedule EIC', 'para_que' => 'Crédito por ingreso del trabajo',
        'siempre' => false, 'ficha' => 'completa', 'orden' => 9,
        'fuente_texto' => 'Schedule EIC de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040sei.pdf',
        'lineas' => [
            ['linea' => '1', 'descripcion' => 'Nombre del hijo', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C1'],
            ['linea' => '2', 'descripcion' => 'SSN del hijo', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C9'],
            ['linea' => '3', 'descripcion' => 'Año de nacimiento del hijo', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C2'],
            ['linea' => '4', 'descripcion' => 'Estudiante o incapacitado (si es mayor)', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C7;C8'],
            ['linea' => '5', 'descripcion' => 'Parentesco', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C3'],
            ['linea' => '6', 'descripcion' => 'Meses que vivió con el contribuyente en EE. UU.', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C4'],
            ['linea' => 'credito', 'descripcion' => 'Crédito por ingreso del trabajo (según la hoja de las instrucciones)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
        ],
    ],
    [
        'codigo' => 'f8962', 'nombre' => 'Form 8962', 'para_que' => 'Crédito por prima del Marketplace',
        'siempre' => false, 'ficha' => 'completa', 'orden' => 10,
        'fuente_texto' => 'Form 8962 de 2025 e instrucciones', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f8962.pdf',
        'lineas' => [
            ['linea' => '1', 'descripcion' => 'Tamaño de la familia fiscal', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '2a', 'descripcion' => 'Ingreso ajustado modificado (del 1040)', 'origen_tipo' => 'linea', 'origen_clave' => '1040:11a'],
            ['linea' => '3', 'descripcion' => 'Ingreso ajustado modificado (cálculo de la línea 2)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '4', 'descripcion' => 'Línea de pobreza federal (tabla de las instrucciones)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '5', 'descripcion' => 'Porcentaje de la línea de pobreza (cálculo de la línea 4)', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => 'col-a', 'descripcion' => 'Primas mensuales (1095-A, Parte III, columna A)', 'origen_tipo' => 'casilla', 'origen_clave' => '1095-a:III-A'],
            ['linea' => 'col-b', 'descripcion' => 'Prima del plan de referencia (1095-A, Parte III, columna B)', 'origen_tipo' => 'casilla', 'origen_clave' => '1095-a:III-B'],
            ['linea' => 'col-f', 'descripcion' => 'Adelanto mensual del crédito (1095-A, Parte III, columna C)', 'origen_tipo' => 'casilla', 'origen_clave' => '1095-a:III-C'],
            ['linea' => '11', 'descripcion' => 'Totales del año (1095-A, línea 33), si todos los meses son iguales', 'origen_tipo' => 'casilla', 'origen_clave' => '1095-a:linea-33'],
            ['linea' => '26', 'descripcion' => 'Crédito neto, si el crédito final es mayor que lo adelantado', 'origen_tipo' => 'calculo', 'origen_clave' => null],
            ['linea' => '29', 'descripcion' => 'Devolución, si se adelantó de más', 'origen_tipo' => 'calculo', 'origen_clave' => null],
        ],
    ],

    // ── Conectores y fichas pendientes: sin líneas por ahora ──
    [
        'codigo' => 'sch-1a', 'nombre' => 'Schedule 1-A', 'para_que' => 'Propinas, horas extra, intereses del vehículo y deducción para mayores',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 11,
        'fuente_texto' => 'Schedule 1-A de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'sch-a', 'nombre' => 'Schedule A', 'para_que' => 'Deducciones detalladas',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 12,
        'fuente_texto' => 'Schedule A de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040sca.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'sch-b', 'nombre' => 'Schedule B', 'para_que' => 'Intereses y dividendos (más de USD 1.500)',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 13,
        'fuente_texto' => 'Schedule B de 2025 (instrucciones del 1040)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'sch-d', 'nombre' => 'Schedule D y Form 8949', 'para_que' => 'Ventas de acciones, cripto o inmuebles',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 14,
        'fuente_texto' => 'Schedule D y Form 8949 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'sch-e', 'nombre' => 'Schedule E', 'para_que' => 'Alquileres y regalías',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 15,
        'fuente_texto' => 'Schedule E de 2025 (Pub. 527)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/p527.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'sch-h', 'nombre' => 'Schedule H', 'para_que' => 'Empleados domésticos',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 16,
        'fuente_texto' => 'Schedule H de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'sch-r', 'nombre' => 'Schedule R', 'para_que' => 'Crédito para mayores o incapacitados',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 17,
        'fuente_texto' => 'Schedule R de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040sr.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'sch-lep', 'nombre' => 'Schedule LEP', 'para_que' => 'Idioma de las cartas del IRS',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 18,
        'fuente_texto' => 'Schedule LEP del 1040 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'f4137', 'nombre' => 'Form 4137', 'para_que' => 'Propinas no reportadas al empleador',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 19,
        'fuente_texto' => 'Form 4137 (instrucciones del 1040 de 2025)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'f4684', 'nombre' => 'Form 4684', 'para_que' => 'Pérdidas por desastre',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 20,
        'fuente_texto' => 'Form 4684 (13614-C)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f13614c.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'f8829', 'nombre' => 'Form 8829', 'para_que' => 'Oficina en casa',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 21,
        'fuente_texto' => 'Form 8829 (Schedule C, línea 30)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040sc.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'f8862', 'nombre' => 'Form 8862', 'para_que' => 'Volver a pedir un crédito negado',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 22,
        'fuente_texto' => 'Form 8862 (13614-C)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f13614c.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'f8863', 'nombre' => 'Form 8863', 'para_que' => 'Créditos educativos',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 23,
        'fuente_texto' => 'Form 8863 (instrucciones del 1040 de 2025)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'f8880', 'nombre' => 'Form 8880', 'para_que' => 'Crédito por ahorro para la jubilación',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 24,
        'fuente_texto' => 'Form 8880 (instrucciones del 1040 de 2025)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'f8888', 'nombre' => 'Form 8888', 'para_que' => 'Dividir el reembolso entre cuentas',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 25,
        'fuente_texto' => 'Form 8888 (instrucciones del 1040 de 2025)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040.pdf',
        'lineas' => [],
    ],
    [
        'codigo' => 'f8889', 'nombre' => 'Form 8889', 'para_que' => 'Cuenta de ahorros médicos (HSA)',
        'siempre' => false, 'ficha' => 'pendiente', 'orden' => 26,
        'fuente_texto' => 'Form 8889 (instrucciones del 1040 de 2025)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
        'lineas' => [],
    ],
];
