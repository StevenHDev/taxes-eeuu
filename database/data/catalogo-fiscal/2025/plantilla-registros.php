<?php

/**
 * Cómo se leen sin IA las hojas `xlsx` y `csv` de los registros del negocio
 * (F6 6.3b, App\Services\Lectura\LectorHojas).
 *
 * Qué línea del Schedule C es cada categoría. Las líneas y sus nombres son los
 * del Schedule C de 2025, página 1 (`tests/Fixtures/irs/2025/pdf/f1040sc.pdf`,
 * el mismo que cita `formularios.php`); la clave es la línea del catálogo
 * (`sch-c`), salvo `1-sin-formulario`, que es el ingreso de la línea 1 que no
 * vino en un 1099 (los 1099 los suma el motor aparte, 6.4).
 *
 * - `plantilla`: las etiquetas de la plantilla de F3.7 (`public/plantillas/registros-negocio-{es,en}.xlsx`),
 *   en español y en inglés, tal como están en la hoja.
 * - `sinonimos`: otros nombres con los que un cliente escribe la categoría en una
 *   hoja propia: los del propio Schedule C y los más comunes. Una categoría que no
 *   está aquí ni en `plantilla` NO se adivina: el renglón queda `no_se` y va a R-14.
 * - `sentido`: de qué lado está la categoría. Una categoría reconocida manda sobre
 *   el signo del monto (muchas hojas listan los gastos en positivo).
 *
 * Todo se compara sin mayúsculas, sin tildes, sin lo que va entre paréntesis y con
 * los espacios juntos (LectorHojas::normalizar), así que aquí se escribe natural.
 *
 * Por verificar (anotado en la entrega de 6.3b):
 * - `Car and truck (gas, maintenance, vehicle insurance)` de la plantilla son gastos reales: van a la
 *   línea 9. Las millas por la tarifa estándar (también línea 9) las calcula el motor con `millas`.
 * - `Rent or lease` no distingue vehículos y maquinaria (20a) de otros bienes (20b): va a 20b.
 * - `Other expenses` va a la 27b ("Other expenses (from line 48)"); la 27a es la deducción por
 *   edificios comerciales eficientes en energía.
 * - `Business meals` va a la 24b con el monto bruto; el porcentaje deducible lo aplica el motor.
 */
return [
    'fuente_texto' => 'Schedule C de 2025, p. 1 (líneas 1, 2 y 8 a 27b)',
    'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040sc.pdf',

    // El título de la plantilla: se busca en las primeras filas de la primera columna.
    'encabezados_plantilla' => [
        'Registro de ingresos y gastos del negocio (Schedule C)',
        'Business income and expense record (Schedule C)',
    ],

    // Las filas de total de la plantilla traen fórmulas: no se leen (el sistema suma).
    'totales' => ['Total (se calcula solo)', 'Total (calculated for you)'],

    // Texto de la plantilla que no es una categoría ni un monto: no cuenta como "no reconocido".
    'estructura' => [
        'Parte I: Ingresos', 'Parte II: Gastos', 'Vehículo y oficina en casa', 'Año fiscal:', 'Monto (USD)', 'Cantidad',
        'Part I: Income', 'Part II: Expenses', 'Vehicle and home office', 'Tax year:', 'Amount (USD)', 'Quantity',
    ],

    'categorias' => [
        ['linea' => '1-sin-formulario', 'sentido' => 'ingreso',
            'plantilla' => ['Ingresos brutos o ventas', 'Gross receipts or sales'],
            'sinonimos' => ['Ingresos', 'Ventas', 'Gross receipts', 'Gross sales', 'Income', 'Sales', 'Revenue']],
        ['linea' => '2', 'sentido' => 'gasto',
            'plantilla' => ['Devoluciones y reembolsos a clientes', 'Returns and refunds to customers'],
            'sinonimos' => ['Devoluciones y descuentos', 'Returns and allowances', 'Returns', 'Refunds']],
        ['linea' => '8', 'sentido' => 'gasto',
            'plantilla' => ['Publicidad', 'Advertising'], 'sinonimos' => ['Anuncios']],
        ['linea' => '9', 'sentido' => 'gasto',
            'plantilla' => ['Auto y camioneta (gasolina, mantenimiento, seguro del vehículo)', 'Car and truck (gas, maintenance, vehicle insurance)'],
            'sinonimos' => ['Car and truck expenses', 'Auto y camioneta', 'Gastos de auto y camioneta']],
        ['linea' => '10', 'sentido' => 'gasto',
            'plantilla' => ['Comisiones y cuotas', 'Commissions and fees'], 'sinonimos' => ['Comisiones']],
        ['linea' => '11', 'sentido' => 'gasto',
            'plantilla' => ['Trabajo contratado (contratistas)', 'Contract labor'], 'sinonimos' => ['Trabajo contratado', 'Contratistas', 'Contractors']],
        ['linea' => '12', 'sentido' => 'gasto',
            'plantilla' => [], 'sinonimos' => ['Agotamiento', 'Depletion']],
        ['linea' => '13', 'sentido' => 'gasto',
            'plantilla' => [], 'sinonimos' => ['Depreciación', 'Depreciation', 'Depreciation and section 179']],
        ['linea' => '14', 'sentido' => 'gasto',
            'plantilla' => [], 'sinonimos' => ['Programas de beneficios para empleados', 'Employee benefit programs']],
        ['linea' => '15', 'sentido' => 'gasto',
            'plantilla' => ['Seguros del negocio', 'Business insurance'], 'sinonimos' => ['Seguros', 'Insurance', 'Insurance (other than health)']],
        ['linea' => '16a', 'sentido' => 'gasto',
            'plantilla' => [], 'sinonimos' => ['Intereses de hipoteca', 'Mortgage interest']],
        ['linea' => '16b', 'sentido' => 'gasto',
            'plantilla' => [], 'sinonimos' => ['Intereses', 'Interest', 'Other interest']],
        ['linea' => '17', 'sentido' => 'gasto',
            'plantilla' => ['Servicios legales y profesionales', 'Legal and professional services'], 'sinonimos' => ['Legal and professional']],
        ['linea' => '18', 'sentido' => 'gasto',
            'plantilla' => ['Gastos de oficina', 'Office expense'], 'sinonimos' => ['Oficina', 'Office expenses', 'Office']],
        ['linea' => '19', 'sentido' => 'gasto',
            'plantilla' => [], 'sinonimos' => ['Planes de pensión', 'Pension and profit-sharing plans']],
        ['linea' => '20a', 'sentido' => 'gasto',
            'plantilla' => [], 'sinonimos' => ['Alquiler de vehículos, maquinaria y equipo', 'Rent vehicles machinery and equipment']],
        ['linea' => '20b', 'sentido' => 'gasto',
            'plantilla' => ['Alquiler o arrendamiento', 'Rent or lease'], 'sinonimos' => ['Alquiler', 'Rent', 'Rent other business property']],
        ['linea' => '21', 'sentido' => 'gasto',
            'plantilla' => ['Reparaciones y mantenimiento', 'Repairs and maintenance'], 'sinonimos' => ['Reparaciones', 'Repairs']],
        ['linea' => '22', 'sentido' => 'gasto',
            'plantilla' => ['Materiales y suministros', 'Supplies'], 'sinonimos' => ['Suministros', 'Materiales']],
        ['linea' => '23', 'sentido' => 'gasto',
            'plantilla' => ['Impuestos y licencias', 'Taxes and licenses'], 'sinonimos' => ['Licencias', 'Taxes and licences']],
        ['linea' => '24a', 'sentido' => 'gasto',
            'plantilla' => ['Viajes', 'Travel'], 'sinonimos' => ['Viaje']],
        ['linea' => '24b', 'sentido' => 'gasto',
            'plantilla' => ['Comidas de negocio', 'Business meals'], 'sinonimos' => ['Comidas', 'Meals', 'Deductible meals']],
        ['linea' => '25', 'sentido' => 'gasto',
            'plantilla' => ['Servicios (luz, agua, internet, teléfono)', 'Utilities (electric, water, internet, phone)'], 'sinonimos' => ['Servicios públicos', 'Utilities']],
        ['linea' => '26', 'sentido' => 'gasto',
            'plantilla' => ['Sueldos y salarios', 'Wages'], 'sinonimos' => ['Sueldos', 'Salarios', 'Wages less employment credits']],
        ['linea' => '27b', 'sentido' => 'gasto',
            'plantilla' => ['Otros gastos (indica cuáles)', 'Other expenses (say which)'], 'sinonimos' => ['Otros gastos', 'Other expenses', 'Other']],
    ],

    // Cantidades que no son dinero: van a las casillas fijas de `registros-ingresos-gastos`.
    'cantidades' => [
        ['clave' => 'millas', 'casilla' => 'millas-negocio', 'etiquetas' => ['Millas recorridas por el negocio', 'Business miles driven', 'Business miles']],
        ['clave' => 'pies-casa', 'casilla' => 'pies-cuadrados-casa', 'etiquetas' => ['Pies cuadrados de tu casa', 'Square feet of your home']],
        ['clave' => 'pies-oficina', 'casilla' => 'pies-cuadrados-oficina', 'etiquetas' => ['Pies cuadrados de la oficina', 'Square feet of the office']],
    ],

    // Encabezados de una hoja propia (una fila de títulos y una fila por movimiento).
    'columnas' => [
        'fecha' => ['Fecha', 'Date', 'Posted date', 'Transaction date', 'Fecha de la transacción'],
        'descripcion' => ['Descripción', 'Description', 'Concepto', 'Detalle', 'Memo', 'Details', 'Payee', 'Beneficiario'],
        'monto' => ['Monto', 'Amount', 'Importe', 'Valor', 'Total'],
        'debito' => ['Débito', 'Debit', 'Cargo', 'Retiro', 'Withdrawal', 'Gasto', 'Expense'],
        'credito' => ['Crédito', 'Credit', 'Abono', 'Depósito', 'Deposit', 'Ingreso', 'Income'],
        'categoria' => ['Categoría', 'Category', 'Rubro', 'Tipo de gasto'],
    ],
];
