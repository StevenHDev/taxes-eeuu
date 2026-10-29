<?php

return [
    [
        'codigo' => 'w2', 'grupo' => 'trabajo', 'nombre_oficial' => 'Form W-2',
        'nombre_sencillo_es' => 'Comprobante de salario', 'uno_por' => 'empleo', 'orden' => 1,
        'fecha_llegada' => '2026-02-02', 'fuente_texto' => 'Instrucciones del 1040',
        'casillas' => [
            ['casilla' => '1', 'descripcion' => 'Salarios', 'tipo_dato' => 'monto', 'clave' => true],
            ['casilla' => '2', 'descripcion' => 'Impuesto federal retenido', 'tipo_dato' => 'monto', 'clave' => true],
        ],
    ],
    [
        'codigo' => '1099-nec', 'grupo' => 'negocio', 'nombre_oficial' => 'Form 1099-NEC',
        'nombre_sencillo_es' => 'Pagos por trabajo independiente', 'uno_por' => 'fuente', 'orden' => 2,
        'fuente_texto' => '1099-NEC de 2025',
        'casillas' => [['casilla' => '1', 'descripcion' => 'Compensación', 'tipo_dato' => 'monto', 'clave' => true]],
    ],
];
