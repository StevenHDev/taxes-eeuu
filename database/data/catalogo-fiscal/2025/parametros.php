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
    [
        'categoria' => 'lectura', 'clave' => 'maximo_paginas_renglones', 'valor' => 40,
        'grupo' => 'topes_umbrales', 'tipo' => 'numero',
        'nombre_es' => 'Lectura con IA de registros del negocio: páginas máximas por archivo',
        'formulario_linea' => 'Regla R-14',
        'condicion_es' => 'Un extracto, recibo o foto de los registros del negocio con más páginas que este número no se lee: el cliente lo sube por meses y la regla R-14 avisa al preparador. Se lee por bloques de 5 páginas (una llamada a la IA por bloque). Las hojas Excel y CSV no tienen este límite (no usan IA). Subirlo solo después de medir el costo (lectura:medir-costo).',
        'fuente_texto' => 'Proceso: decisión de Steven (9 y 11 nov. 2026)',
        'fuente_url' => null,
    ],
    [
        'categoria' => 'lectura', 'clave' => 'maximo_paginas_ia_por_cliente', 'valor' => 120,
        'grupo' => 'topes_umbrales', 'tipo' => 'numero',
        'nombre_es' => 'Lectura con IA de registros del negocio: tope de páginas por cliente y año',
        'formulario_linea' => 'Regla R-14',
        'condicion_es' => 'Suma de páginas leídas con IA de los registros del negocio de un cliente en el año. Un archivo que haría pasar este tope no se manda a la IA: queda sin leer y la regla R-14 le pide al preparador decidir (subir el tope aquí o pedir la plantilla). 120 son unos 3 extractos de 40 páginas; se ajusta después de medir el costo.',
        'fuente_texto' => 'Proceso: decisión de Steven (9 y 11 nov. 2026)',
        'fuente_url' => null,
    ],
    [
        'categoria' => 'self_employment_tax', 'clave' => 'maximo_seguro_social_retenido_por_persona', 'valor' => 10918.2,
        'grupo' => 'topes_umbrales', 'tipo' => 'monto',
        'nombre_es' => 'Seguro Social retenido por persona con varios empleadores: máximo del año',
        'formulario_linea' => 'Schedule 3, línea 11',
        'condicion_es' => 'Si una persona tuvo dos o más empleadores y el Seguro Social retenido en sus W-2 (casilla 4) suma más que este monto (6,2 % del tope del Seguro Social), el exceso es un crédito en el Schedule 3 (regla R-05).',
        'fuente_texto' => 'Instrucciones del 1040 de 2025; Schedule 3, línea 11',
        'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
    ],
    [
        'categoria' => 'vencimientos', 'clave' => 'presentacion', 'valor' => '2026-04-15',
        'grupo' => 'topes_umbrales', 'tipo' => 'fecha',
        'nombre_es' => 'Fecha límite para presentar la declaración del año',
        'formulario_linea' => 'Form 1040',
        'condicion_es' => 'Ordena la cola del preparador por vencimiento: el expediente que vence primero aparece primero.',
        'fuente_texto' => 'IRC §6072(a); instrucciones del 1040 de 2025',
        'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
    ],
    [
        'categoria' => 'vencimientos', 'clave' => 'extension', 'valor' => '2026-10-15',
        'grupo' => 'topes_umbrales', 'tipo' => 'fecha',
        'nombre_es' => 'Fecha límite con la extensión (Form 4868)',
        'formulario_linea' => 'Form 4868',
        'condicion_es' => 'Aplica si el cliente respondió "sí" a G2 (pidió extensión). Ordena la cola del preparador por vencimiento.',
        'fuente_texto' => 'Form 4868 e instrucciones',
        'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f4868.pdf',
    ],
];
