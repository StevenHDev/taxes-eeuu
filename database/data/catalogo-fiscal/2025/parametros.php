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
];
