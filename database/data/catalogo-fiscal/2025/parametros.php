<?php

/**
 * Parámetros del año fiscal 2025 que NO están en ParametrosFiscalesSeeder.
 *
 * Origen: docs/mejora-plataforma/documento-madre.md, sección 3 (tabla "Valores que cambian cada año",
 * fila "Mayor de 65 (deducción adicional)": nació antes del 2 ene. 1961). El límite de ingreso del
 * pariente calificado (USD 5.200) ya existe en el seeder y no se repite aquí.
 */
return [
    [
        'categoria' => 'edad', 'clave' => 'nacido_antes_de_mayor_65', 'valor' => '1961-01-02',
        'grupo' => 'deduccion_estandar', 'tipo' => 'fecha',
        'nombre_es' => 'Mayor de 65 (deducción estándar adicional): nació antes del 2 de enero',
        'formulario_linea' => 'Form 1040, línea 12d',
        'condicion_es' => null,
        'fuente_texto' => 'Form 1040 de 2025, línea 12d (documento madre, sección 3)',
        'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040.pdf',
    ],
    [
        'categoria' => 'lectura', 'clave' => 'umbral_confianza', 'valor' => 0.9,
        'grupo' => 'topes_umbrales', 'tipo' => 'numero',
        'nombre_es' => 'Lectura de documentos: confianza mínima para no pedir revisión',
        'formulario_linea' => 'Regla R-03',
        'condicion_es' => 'Un dato de una casilla clave leído con una confianza menor que este número va a revisión del preparador (regla R-03). La confianza la calcula la plataforma: 0,99 si el valor está en el texto del PDF, 0,90 si se leyó con claridad, 0,60 si hubo dudas o el formato no cuadra, 0 si no se ve. Subirlo manda más datos a revisión; bajarlo, menos.',
        'fuente_texto' => 'Proceso (umbral propuesto, a calibrar)',
        'fuente_url' => null,
    ],
];
